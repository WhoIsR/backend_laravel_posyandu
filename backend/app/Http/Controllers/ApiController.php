<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nik_nip' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('nik_nip', $data['nik_nip'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'NIK/NIP atau password belum sesuai.'], 401);
        }

        if ($user->status !== 'aktif') {
            return response()->json(['message' => 'Akun ini sedang nonaktif. Hubungi admin Posyandu.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $this->publicUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->publicUser($request->user()));
    }

    public function adminListUsers(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireAdmin($request)) {
            return $forbidden;
        }

        $rows = User::query()
            ->select(['id', 'nama', 'nik_nip', 'role', 'posyandu_id', 'status', 'created_at', 'updated_at'])
            ->whereIn('role', ['admin', 'bidan', 'kader'])
            ->orderBy('role')
            ->orderBy('nama')
            ->paginate($this->perPage($request));

        return response()->json($this->paginated($rows));
    }

    public function adminStoreUser(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireAdmin($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'nama' => ['required', 'string'],
            'nik_nip' => ['required', 'string', 'unique:users,nik_nip'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:bidan,kader'],
            'posyandu_id' => ['nullable', 'exists:posyandu,id'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $user = User::query()->create([
            'nama' => $data['nama'],
            'nik_nip' => $data['nik_nip'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'posyandu_id' => $data['posyandu_id'] ?? null,
            'status' => $data['status'],
        ]);

        return response()->json($this->publicUser($user), 201);
    }

    public function adminUpdateUser(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireAdmin($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'nama' => ['required', 'string'],
            'role' => ['required', 'in:bidan,kader'],
            'posyandu_id' => ['nullable', 'exists:posyandu,id'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $update = [
            'nama' => $data['nama'],
            'role' => $data['role'],
            'posyandu_id' => $data['posyandu_id'] ?? null,
            'status' => $data['status'],
        ];
        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        User::query()->whereKey($id)->whereIn('role', ['bidan', 'kader'])->update($update);

        return response()->json($this->publicUser(User::query()->findOrFail($id)));
    }

    public function adminListPosyandu(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireAdmin($request)) {
            return $forbidden;
        }

        return response()->json($this->paginated(
            DB::table('posyandu')->orderBy('nama_posyandu')->paginate($this->perPage($request))
        ));
    }

    public function adminStorePosyandu(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireAdmin($request)) {
            return $forbidden;
        }

        $data = $this->posyanduPayload($request);
        $id = DB::table('posyandu')->insertGetId($this->withTimestamps($data));

        return response()->json($this->row('posyandu', $id), 201);
    }

    public function adminUpdatePosyandu(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireAdmin($request)) {
            return $forbidden;
        }

        DB::table('posyandu')->where('id', $id)->update($this->touch($this->posyanduPayload($request)));

        return response()->json($this->rowOrFail('posyandu', $id));
    }

    public function listBalita(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $latestPengukuran = DB::table('pengukuran as latest')
            ->select('latest.balita_id', 'latest.berat_badan', 'latest.tinggi_badan', 'latest.tanggal_ukur')
            ->whereRaw('latest.id = (select p2.id from pengukuran p2 where p2.balita_id = latest.balita_id order by p2.tanggal_ukur desc, p2.id desc limit 1)');

        $query = DB::table('balita')
            ->leftJoinSub($latestPengukuran, 'latest_pengukuran', 'latest_pengukuran.balita_id', '=', 'balita.id')
            ->select('balita.*', 'latest_pengukuran.berat_badan as latest_weight', 'latest_pengukuran.tinggi_badan as latest_height', 'latest_pengukuran.tanggal_ukur as latest_measured_at')
            ->orderBy('balita.nama_balita');

        if ($request->user()->role === 'kader') {
            $query->where('balita.posyandu_id', $request->user()->posyandu_id);
        } elseif ($request->filled('posyandu_id')) {
            $query->where('balita.posyandu_id', $request->integer('posyandu_id'));
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('balita.nama_balita', 'like', "%{$search}%")
                    ->orWhere('balita.nama_ibu', 'like', "%{$search}%")
                    ->orWhere('balita.nik_balita', 'like', "%{$search}%");
            });
        }

        return response()->json($this->paginated($query->paginate($perPage)));
    }

    public function storeBalita(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidanOrKader($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'nama_balita' => ['required', 'string'],
            'nik_balita' => ['nullable', 'string'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'nama_ibu' => ['required', 'string'],
            'nik_ibu' => ['nullable', 'string'],
            'alamat' => ['required', 'string'],
            'penghasilan' => ['required', 'integer', 'min:0'],
            'jumlah_keluarga' => ['required', 'integer', 'min:1'],
            'posyandu_id' => ['required', 'exists:posyandu,id'],
        ]);

        if (Carbon::parse($data['tanggal_lahir'])->diffInMonths(now()) > 59) {
            return response()->json(['message' => 'Umur balita yang diproses sistem dibatasi 0-59 bulan.'], 422);
        }

        $id = DB::table('balita')->insertGetId($this->withTimestamps($data));

        return response()->json($this->row('balita', $id), 201);
    }

    public function showBalita(Request $request, int $id): JsonResponse
    {
        $balita = $this->rowOrFail('balita', $id);
        $this->checkBalitaAccess($request, $balita);
        return response()->json($balita);
    }

    public function updateBalita(Request $request, int $id): JsonResponse
    {
        $balita = $this->rowOrFail('balita', $id);
        $this->checkBalitaAccess($request, $balita);

        $data = $request->validate([
            'nama_balita' => ['sometimes', 'required', 'string'],
            'nik_balita' => ['nullable', 'string'],
            'tanggal_lahir' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['sometimes', 'required', 'in:L,P'],
            'nama_ibu' => ['required', 'string'],
            'nik_ibu' => ['nullable', 'string'],
            'alamat' => ['required', 'string'],
            'penghasilan' => ['required', 'integer', 'min:0'],
            'jumlah_keluarga' => ['required', 'integer', 'min:1'],
            'posyandu_id' => ['required', 'exists:posyandu,id'],
        ]);

        if (isset($data['tanggal_lahir']) && Carbon::parse($data['tanggal_lahir'])->diffInMonths(now()) > 59) {
            return response()->json(['message' => 'Umur balita yang diproses sistem dibatasi 0-59 bulan.'], 422);
        }

        DB::table('balita')->where('id', $id)->update($this->touch($data));

        return response()->json($this->rowOrFail('balita', $id));
    }

    public function listJadwal(Request $request): JsonResponse
    {
        $query = DB::table('jadwal_posyandu')->orderBy('tanggal');

        if ($request->user()->role === 'kader') {
            $query->where('posyandu_id', $request->user()->posyandu_id);
        }

        return response()->json($this->paginated($query->paginate($this->perPage($request))));
    }

    public function storeJadwal(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'posyandu_id' => ['required', 'exists:posyandu,id'],
            'tanggal' => ['required', 'date'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
            'lokasi' => ['required', 'string'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $id = DB::table('jadwal_posyandu')->insertGetId($this->withTimestamps($data + ['notif_h1_sent' => false]));

        return response()->json($this->row('jadwal_posyandu', $id), 201);
    }

    public function updateJadwal(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'posyandu_id' => ['required', 'exists:posyandu,id'],
            'tanggal' => ['required', 'date'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
            'lokasi' => ['required', 'string'],
            'keterangan' => ['nullable', 'string'],
        ]);

        DB::table('jadwal_posyandu')->where('id', $id)->update($this->touch($data));

        return response()->json($this->rowOrFail('jadwal_posyandu', $id));
    }

    public function storeSesi(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidanOrKader($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'jadwal_posyandu_id' => ['nullable', 'exists:jadwal_posyandu,id'],
            'posyandu_id' => ['required', 'exists:posyandu,id'],
            'tanggal' => ['required', 'date'],
        ]);

        $id = DB::table('sesi_posyandu')->insertGetId($this->withTimestamps($data + [
            'status' => 'berjalan',
            'dibuka_oleh' => $request->user()->id,
        ]));

        return response()->json($this->row('sesi_posyandu', $id), 201);
    }

    public function sesiAktif(Request $request): JsonResponse
    {
        $row = DB::table('sesi_posyandu')
            ->where('status', 'berjalan')
            ->when($request->user()->posyandu_id, fn ($q) => $q->where('posyandu_id', $request->user()->posyandu_id))
            ->orderByDesc('tanggal')
            ->first();

        return response()->json($row);
    }

    public function closeSesi(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireBidanOrKader($request)) {
            return $forbidden;
        }

        DB::table('sesi_posyandu')->where('id', $id)->update($this->touch(['status' => 'selesai']));

        return response()->json($this->rowOrFail('sesi_posyandu', $id));
    }

    public function storePengukuran(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'kader') {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $data = $request->validate([
            'sesi_posyandu_id' => ['required', 'exists:sesi_posyandu,id'],
            'balita_id' => ['required', 'exists:balita,id'],
            'berat_badan' => ['required', 'numeric', 'between:1,40'],
            'tinggi_badan' => ['required', 'numeric', 'between:30,130'],
        ]);

        if (DB::table('pengukuran')->where('sesi_posyandu_id', $data['sesi_posyandu_id'])->where('balita_id', $data['balita_id'])->exists()) {
            return response()->json(['message' => 'Balita ini sudah dicatat pada sesi hari ini.'], 422);
        }

        $id = DB::table('pengukuran')->insertGetId($this->withTimestamps([
            'sesi_posyandu_id' => $data['sesi_posyandu_id'],
            'balita_id' => $data['balita_id'],
            'kader_id' => $request->user()->id,
            'tanggal_ukur' => now()->toDateString(),
            'berat_badan' => $data['berat_badan'],
            'tinggi_badan' => $data['tinggi_badan'],
            'status_prediksi' => 'menunggu',
        ]));

        $this->processPrediction($id);

        return response()->json($this->pengukuranPayload($id), 201);
    }

    public function retryPrediksi(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireBidanOrKader($request)) {
            return $forbidden;
        }

        $this->processPrediction($id);

        return response()->json($this->pengukuranPayload($id));
    }

    public function skrining(Request $request, int $sesiId): JsonResponse
    {
        $rows = DB::table('pengukuran')
            ->leftJoin('balita', 'balita.id', '=', 'pengukuran.balita_id')
            ->leftJoin('hasil_prediksi', 'hasil_prediksi.pengukuran_id', '=', 'pengukuran.id')
            ->leftJoin('rujukan', 'rujukan.pengukuran_id', '=', 'pengukuran.id')
            ->where('pengukuran.sesi_posyandu_id', $sesiId)
            ->select('pengukuran.*', 'balita.nama_balita', 'balita.nama_ibu', 'hasil_prediksi.risk_level', 'rujukan.status_rujukan')
            ->orderByDesc('pengukuran.id')
            ->paginate($this->perPage($request));

        $rows->getCollection()->transform(function ($row) {
            $continuity = $this->continuitySummary((int) $row->balita_id, (int) $row->id);
            $row->continuity_summary = $continuity;
            $row->model_risk_level = $row->risk_level;
            $row->overall_risk_level = $this->highestRisk($row->risk_level, $continuity['risk_level']);
            $row->risk_level = $row->overall_risk_level;
            return $row;
        });

        return response()->json($this->paginated($rows));
    }

    public function listRujukan(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $query = DB::table('rujukan')
            ->join('balita', 'balita.id', '=', 'rujukan.balita_id')
            ->join('pengukuran', 'pengukuran.id', '=', 'rujukan.pengukuran_id')
            ->join('hasil_prediksi', 'hasil_prediksi.id', '=', 'rujukan.hasil_prediksi_id')
            ->select(
                'rujukan.*',
                'balita.nama_balita',
                'balita.nama_ibu',
                'balita.tanggal_lahir',
                'balita.posyandu_id',
                'pengukuran.berat_badan',
                'pengukuran.tinggi_badan',
                'pengukuran.tanggal_ukur',
                'hasil_prediksi.risk_level'
            )
            ->orderByRaw("case hasil_prediksi.risk_level when 'tinggi' then 1 when 'sedang' then 2 else 3 end")
            ->orderByDesc('rujukan.created_at');

        if ($request->user()->role === 'bidan' && $request->user()->posyandu_id) {
            $query->where('balita.posyandu_id', $request->user()->posyandu_id);
        }

        if ($request->filled('status')) {
            $query->where('rujukan.status_rujukan', $request->string('status'));
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('balita.nama_balita', 'like', "%{$search}%")
                    ->orWhere('balita.nama_ibu', 'like', "%{$search}%");
            });
        }

        return response()->json($this->paginated($query->paginate($this->perPage($request))));
    }

    public function showRujukan(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $row = DB::table('rujukan')
            ->join('balita', 'balita.id', '=', 'rujukan.balita_id')
            ->join('pengukuran', 'pengukuran.id', '=', 'rujukan.pengukuran_id')
            ->join('hasil_prediksi', 'hasil_prediksi.id', '=', 'rujukan.hasil_prediksi_id')
            ->where('rujukan.id', $id)
            ->select('rujukan.*', 'balita.nama_balita', 'balita.nama_ibu', 'balita.tanggal_lahir', 'balita.posyandu_id', 'pengukuran.berat_badan', 'pengukuran.tinggi_badan', 'pengukuran.tanggal_ukur', 'hasil_prediksi.risk_level', 'hasil_prediksi.probability_json', 'hasil_prediksi.model_version')
            ->first();

        abort_if(! $row, 404);

        if ($request->user()->role === 'bidan' && $request->user()->posyandu_id) {
            abort_if($row->posyandu_id !== $request->user()->posyandu_id, 403, 'Akses ditolak.');
        }

        return response()->json($row);
    }

    public function storeValidasi(Request $request, int $rujukanId): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $rujukan = $this->rowOrFail('rujukan', $rujukanId);
        $balita = $this->rowOrFail('balita', $rujukan->balita_id);
        if ($request->user()->role === 'bidan' && $request->user()->posyandu_id) {
            abort_if($balita->posyandu_id !== $request->user()->posyandu_id, 403, 'Akses ditolak.');
        }

        $data = $request->validate([
            'keputusan' => ['required', 'in:observasi,konseling,pmt,rujuk_puskesmas,cek_ulang_data'],
            'catatan_bidan' => ['required', 'string'],
        ]);

        $id = DB::table('validasi_medis')->insertGetId($this->withTimestamps([
            'rujukan_id' => $rujukanId,
            'bidan_id' => $request->user()->id,
            'keputusan' => $data['keputusan'],
            'catatan_bidan' => $data['catatan_bidan'],
            'tanggal_validasi' => now()->toDateString(),
        ]));

        DB::table('rujukan')->where('id', $rujukanId)->update($this->touch([
            'status_rujukan' => $data['keputusan'] === 'cek_ulang_data' ? 'perlu_cek_ulang' : 'divalidasi',
        ]));

        $pengukuran = $this->row('pengukuran', $rujukan->pengukuran_id);
        if ($pengukuran) {
            $bidanName = $request->user()->nama ?: 'Bidan';
            $balitaName = DB::table('balita')->where('id', $rujukan->balita_id)->value('nama_balita') ?: 'Balita';
            $decText = match ($data['keputusan']) {
                'observasi' => 'Observasi',
                'konseling' => 'Konseling',
                'pmt' => 'PMT',
                'rujuk_puskesmas' => 'Rujuk Puskesmas',
                'cek_ulang_data' => 'Cek Ulang Data',
                default => $data['keputusan']
            };
            $msg = sprintf("%s telah memvalidasi rujukan %s dengan keputusan: %s.", $bidanName, $balitaName, $decText);
            $this->notify($pengukuran->kader_id, 'Validasi Selesai', $msg, 'validasi_selesai', ['rujukan_id' => $rujukanId]);
        }

        return response()->json($this->row('validasi_medis', $id), 201);
    }

    public function listPmt(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        return response()->json($this->paginated(DB::table('katalog_pmt')->orderBy('nama_barang')->paginate($this->perPage($request))));
    }

    public function storePmt(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'nama_barang' => ['required', 'string'],
            'jenis_barang' => ['required', 'string'],
            'satuan' => ['required', 'string'],
            'stok_saat_ini' => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
        ]);

        $id = DB::table('katalog_pmt')->insertGetId($this->withTimestamps($data));

        return response()->json($this->row('katalog_pmt', $id), 201);
    }

    public function updatePmt(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'nama_barang' => ['required', 'string'],
            'jenis_barang' => ['required', 'string'],
            'satuan' => ['required', 'string'],
            'stok_saat_ini' => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
        ]);

        DB::table('katalog_pmt')->where('id', $id)->update($this->touch($data));

        return response()->json($this->rowOrFail('katalog_pmt', $id));
    }

    public function distribusiPmt(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireBidan($request)) {
            return $forbidden;
        }

        $data = $request->validate([
            'validasi_medis_id' => ['required', 'exists:validasi_medis,id'],
            'balita_id' => ['required', 'exists:balita,id'],
            'pmt_id' => ['required', 'exists:katalog_pmt,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'tanggal_distribusi' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $balita = $this->rowOrFail('balita', $data['balita_id']);
        if ($request->user()->role === 'bidan' && $request->user()->posyandu_id) {
            abort_if($balita->posyandu_id !== $request->user()->posyandu_id, 403, 'Akses ditolak.');
        }

        $validation = $this->row('validasi_medis', $data['validasi_medis_id']);
        if ($validation->keputusan !== 'pmt') {
            return response()->json(['message' => 'Distribusi hanya dapat dibuat dari validasi medis yang membutuhkan PMT.'], 422);
        }

        $id = DB::transaction(function () use ($request, $data) {
            $pmt = DB::table('katalog_pmt')->where('id', $data['pmt_id'])->lockForUpdate()->first();
            if (! $pmt || $pmt->stok_saat_ini < $data['jumlah']) {
                abort(422, 'Stok tidak cukup untuk jumlah ini.');
            }

            DB::table('katalog_pmt')->where('id', $data['pmt_id'])->update($this->touch([
                'stok_saat_ini' => $pmt->stok_saat_ini - $data['jumlah'],
            ]));

            return DB::table('distribusi_pmt')->insertGetId($this->withTimestamps($data + [
                'bidan_id' => $request->user()->id,
            ]));
        });

        $validation = $this->row('validasi_medis', $data['validasi_medis_id']);
        $rujukan = $this->row('rujukan', $validation->rujukan_id);
        $pengukuran = $this->row('pengukuran', $rujukan->pengukuran_id);
        if ($pengukuran) {
            $bidanName = $request->user()->nama ?: 'Bidan';
            $balitaName = DB::table('balita')->where('id', $rujukan->balita_id)->value('nama_balita') ?: 'Balita';
            $pmtName = DB::table('katalog_pmt')->where('id', $data['pmt_id'])->value('nama_barang') ?: 'PMT';
            $msg = sprintf("Bidan %s telah menyetujui distribusi PMT (%s) untuk %s.", $bidanName, $pmtName, $balitaName);
            $this->notify($pengukuran->kader_id, 'PMT Disetujui', $msg, 'pmt_disetujui', ['distribusi_pmt_id' => $id]);
        }

        return response()->json($this->row('distribusi_pmt', $id), 201);
    }

    public function listNotifikasi(Request $request): JsonResponse
    {
        $rows = DB::table('notifikasi')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json($this->paginated($rows, fn ($row) => $this->notificationPayload($row)));
    }

    public function readNotifikasi(Request $request, int $id): JsonResponse
    {
        $notif = DB::table('notifikasi')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if(! $notif, 404);

        DB::table('notifikasi')
            ->where('id', $id)
            ->update($this->touch(['is_read' => true]));

        $notif->is_read = 1;

        return response()->json($this->notificationPayload($notif));
    }

    public function storeAnalytics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:100'],
            'properties' => ['nullable', 'array'],
        ]);

        $user = $request->user('sanctum');

        $id = DB::table('analytics_events')->insertGetId([
            'user_id' => $user ? $user->id : null,
            'event_name' => $data['event_name'],
            'properties' => isset($data['properties']) ? json_encode($data['properties']) : null,
            'created_at' => now(),
        ]);

        $row = DB::table('analytics_events')->where('id', $id)->first();
        if ($row && isset($row->properties)) {
            $row->properties = json_decode($row->properties, true);
        }

        return response()->json($row, 201);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['required', 'string']]);
        User::query()->whereKey($request->user()->id)->update($data);

        return response()->json($this->publicUser($request->user()->fresh()));
    }

    public function report(Request $request, string $type)
    {
        if ($forbidden = $this->requireBidanOrAdmin($request)) {
            return $forbidden;
        }

        abort_unless(in_array($type, ['prediksi', 'kehadiran', 'distribusi-pmt', 'semua'], true), 404);

        $validator = Validator::make($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Rentang tanggal laporan belum valid.', 'errors' => $validator->errors()], 422);
        }

        $filename = 'laporan-'.$type.'-'.now()->format('YmdHis').'.pdf';

        if ($type === 'semua') {
            [$predCols, $predRows] = $this->reportData('prediksi', $request->query('start_date'), $request->query('end_date'));
            [$kehCols, $kehRows] = $this->reportData('kehadiran', $request->query('start_date'), $request->query('end_date'));
            [$pmtCols, $pmtRows] = $this->reportData('distribusi-pmt', $request->query('start_date'), $request->query('end_date'));

            $pdf = Pdf::loadHTML(view('reports.semua', [
                'start' => $request->query('start_date', '-'),
                'end' => $request->query('end_date', '-'),
                'prediksi' => ['columns' => $predCols, 'rows' => $predRows],
                'kehadiran' => ['columns' => $kehCols, 'rows' => $kehRows],
                'pmt' => ['columns' => $pmtCols, 'rows' => $pmtRows],
            ])->render());
        } else {
            $titles = [
                'prediksi' => 'Laporan Prediksi Risiko',
                'kehadiran' => 'Laporan Kehadiran Posyandu',
                'distribusi-pmt' => 'Laporan Distribusi PMT',
            ];

            [$columns, $rows] = $this->reportData($type, $request->query('start_date'), $request->query('end_date'));
            $pdf = Pdf::loadHTML(view('reports.basic', [
                'title' => $titles[$type],
                'start' => $request->query('start_date', '-'),
                'end' => $request->query('end_date', '-'),
                'columns' => $columns,
                'rows' => $rows,
            ])->render());
        }

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    private function reportData(string $type, ?string $start, ?string $end): array
    {
        $startDate = $start ?: '1900-01-01';
        $endDate = $end ?: now()->toDateString();

        if ($type === 'prediksi') {
            $rows = DB::table('hasil_prediksi')
                ->join('pengukuran', 'pengukuran.id', '=', 'hasil_prediksi.pengukuran_id')
                ->join('balita', 'balita.id', '=', 'pengukuran.balita_id')
                ->whereBetween('pengukuran.tanggal_ukur', [$startDate, $endDate])
                ->orderByDesc('pengukuran.tanggal_ukur')
                ->limit(200)
                ->get(['pengukuran.tanggal_ukur', 'balita.nama_balita', 'balita.nama_ibu', 'hasil_prediksi.risk_level', 'hasil_prediksi.risk_score'])
                ->map(fn ($row) => [
                    $row->tanggal_ukur,
                    $row->nama_balita,
                    $row->nama_ibu,
                    $this->riskText($row->risk_level),
                    $row->risk_score ?? '-',
                ])->all();

            return [['Tanggal', 'Balita', 'Ibu', 'Status Skrining', 'Skor'], $rows];
        }

        if ($type === 'kehadiran') {
            $rows = DB::table('pengukuran')
                ->join('sesi_posyandu', 'sesi_posyandu.id', '=', 'pengukuran.sesi_posyandu_id')
                ->join('balita', 'balita.id', '=', 'pengukuran.balita_id')
                ->whereBetween('pengukuran.tanggal_ukur', [$startDate, $endDate])
                ->orderByDesc('pengukuran.tanggal_ukur')
                ->limit(200)
                ->get(['sesi_posyandu.tanggal', 'balita.nama_balita', 'balita.nama_ibu', 'pengukuran.berat_badan', 'pengukuran.tinggi_badan'])
                ->map(fn ($row) => [
                    $row->tanggal,
                    $row->nama_balita,
                    $row->nama_ibu,
                    $row->berat_badan.' kg',
                    $row->tinggi_badan.' cm',
                ])->all();

            return [['Tanggal', 'Balita', 'Ibu', 'BB', 'TB'], $rows];
        }

        $rows = DB::table('distribusi_pmt')
            ->join('balita', 'balita.id', '=', 'distribusi_pmt.balita_id')
            ->join('katalog_pmt', 'katalog_pmt.id', '=', 'distribusi_pmt.pmt_id')
            ->whereBetween('distribusi_pmt.tanggal_distribusi', [$startDate, $endDate])
            ->orderByDesc('distribusi_pmt.tanggal_distribusi')
            ->limit(200)
            ->get(['distribusi_pmt.tanggal_distribusi', 'balita.nama_balita', 'katalog_pmt.nama_barang', 'distribusi_pmt.jumlah', 'katalog_pmt.satuan'])
            ->map(fn ($row) => [
                $row->tanggal_distribusi,
                $row->nama_balita,
                $row->nama_barang,
                $row->jumlah.' '.$row->satuan,
            ])->all();

        return [['Tanggal', 'Balita', 'PMT', 'Jumlah'], $rows];
    }

    private function processPrediction(int $pengukuranId): void
    {
        $pengukuran = $this->rowOrFail('pengukuran', $pengukuranId);
        $balita = $this->rowOrFail('balita', $pengukuran->balita_id);
        $umurBulan = Carbon::parse($balita->tanggal_lahir)->diffInMonths(Carbon::parse($pengukuran->tanggal_ukur));
        $features = [
            'Umur (bulan)' => $umurBulan,
            'Jenis Kelamin Encoded' => $balita->jenis_kelamin === 'L' ? 1 : 0,
            'Tinggi Badan (cm)' => (float) $pengukuran->tinggi_badan,
            'Kelompok Usia' => $this->kelompokUsia($umurBulan),
            'TB per Bulan' => round(((float) $pengukuran->tinggi_badan) / ($umurBulan + 1), 4),
            'Penghasilan' => $this->kategoriPenghasilan((int) $balita->penghasilan),
            'Jumlah Keluarga' => (int) $balita->jumlah_keluarga,
        ];

        DB::table('pengukuran')->where('id', $pengukuranId)->update($this->touch(['status_prediksi' => 'diproses']));

        try {
            $response = Http::timeout((int) config('services.ml_api.timeout'))
                ->post(config('services.ml_api.url').'/predict', ['features' => $features]);

            if (! $response->successful()) {
                throw new \RuntimeException('ML API gagal.');
            }

            $body = $response->json();
            $probability = $body['probability'] ?? [];
            $riskLevel = $body['risk_level'] ?? $this->riskFromClass((int) ($body['predicted_class'] ?? 0));
            $predictionId = DB::table('hasil_prediksi')->insertGetId($this->withTimestamps([
                'pengukuran_id' => $pengukuranId,
                'umur_bulan' => $features['Umur (bulan)'],
                'jenis_kelamin_encoded' => $features['Jenis Kelamin Encoded'],
                'tinggi_badan' => $features['Tinggi Badan (cm)'],
                'kelompok_usia' => $features['Kelompok Usia'],
                'tb_per_bulan' => $features['TB per Bulan'],
                'penghasilan' => $features['Penghasilan'],
                'jumlah_keluarga' => $features['Jumlah Keluarga'],
                'predicted_class' => (int) ($body['predicted_class'] ?? 0),
                'risk_level' => $riskLevel,
                'risk_score' => $probability ? max($probability) : null,
                'probability_json' => json_encode($probability),
                'model_version' => $body['model_version'] ?? 'xgboost_v1',
            ]));

            DB::table('pengukuran')->where('id', $pengukuranId)->update($this->touch(['status_prediksi' => 'selesai']));

            $continuity = $this->continuitySummary((int) $balita->id, $pengukuranId);
            $overallRiskLevel = $this->highestRisk($riskLevel, $continuity['risk_level']);

            if (in_array($overallRiskLevel, ['sedang', 'tinggi'], true) && ! DB::table('rujukan')->where('pengukuran_id', $pengukuranId)->exists()) {
                $rujukanId = DB::table('rujukan')->insertGetId($this->withTimestamps([
                    'balita_id' => $balita->id,
                    'pengukuran_id' => $pengukuranId,
                    'hasil_prediksi_id' => $predictionId,
                    'status_rujukan' => 'menunggu_validasi',
                    'tanggal_rujukan' => now()->toDateString(),
                ]));

                $posyandu = $this->row('posyandu', $balita->posyandu_id);
                if ($posyandu?->bidan_id) {
                    $kaderName = DB::table('users')->where('id', $pengukuran->kader_id)->value('nama') ?: 'Kader';
                    $riskLabel = $overallRiskLevel === 'tinggi' ? 'Tinggi' : 'Sedang';
                    $msg = sprintf(
                        "%s memasukkan pengukuran untuk %s - BB: %s kg, TB: %s cm (Skrining: %s)",
                        $kaderName,
                        $balita->nama_balita,
                        round($pengukuran->berat_badan, 1),
                        round($pengukuran->tinggi_badan, 1),
                        $riskLabel
                    );
                    $this->notify($posyandu->bidan_id, 'Rujukan Baru', $msg, 'rujukan_masuk', [
                        'rujukan_id' => $rujukanId,
                        'nama_kader' => $kaderName,
                        'nama_balita' => $balita->nama_balita,
                        'berat_badan' => (float)$pengukuran->berat_badan,
                        'tinggi_badan' => (float)$pengukuran->tinggi_badan,
                        'risk_level' => $overallRiskLevel,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            DB::table('pengukuran')->where('id', $pengukuranId)->update($this->touch(['status_prediksi' => 'gagal']));
        }
    }

    private function pengukuranPayload(int $id): array
    {
        $pengukuran = (array) $this->rowOrFail('pengukuran', $id);
        $hasil = DB::table('hasil_prediksi')->where('pengukuran_id', $id)->orderByDesc('id')->first();
        $rujukan = DB::table('rujukan')->where('pengukuran_id', $id)->orderByDesc('id')->first();

        if ($hasil) {
            $hasil->probability_json = json_decode($hasil->probability_json, true);
        }

        return $pengukuran + [
            'hasil_prediksi' => $hasil,
            'rujukan' => $rujukan,
            'continuity_summary' => $continuity = $this->continuitySummary((int) $pengukuran['balita_id'], $id),
            'model_risk_level' => $hasil?->risk_level,
            'overall_risk_level' => $this->highestRisk($hasil?->risk_level, $continuity['risk_level']),
            'risk_level' => $this->highestRisk($hasil?->risk_level, $continuity['risk_level']),
        ];
    }

    private function continuitySummary(int $balitaId, int $untilPengukuranId): array
    {
        // ponytail: heuristic overlay; replace with a retrained longitudinal model when labeled history data exists.
        $rows = DB::table('pengukuran')
            ->where('balita_id', $balitaId)
            ->where('id', '<=', $untilPengukuranId)
            ->orderByDesc('tanggal_ukur')
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'tanggal_ukur', 'berat_badan', 'tinggi_badan'])
            ->reverse()
            ->values();

        if ($rows->count() < 2) {
            return [
                'risk_level' => 'rendah',
                'label' => 'Data awal',
                'data_points' => $rows->count(),
                'message' => 'Belum ada tren bulanan. Gunakan sebagai baseline dan ukur ulang bulan berikutnya.',
            ];
        }

        $first = $rows->first();
        $latest = $rows->last();
        $previous = $rows[$rows->count() - 2];
        $days = max(1, Carbon::parse($first->tanggal_ukur)->diffInDays(Carbon::parse($latest->tanggal_ukur)));
        $months = max(0.1, $days / 30);
        $weightDelta = round((float) $latest->berat_badan - (float) $previous->berat_badan, 2);
        $heightDelta = round((float) $latest->tinggi_badan - (float) $previous->tinggi_badan, 2);
        $heightVelocity = round(((float) $latest->tinggi_badan - (float) $first->tinggi_badan) / $months, 2);

        $risk = 'rendah';
        $label = 'Tren stabil';
        $message = 'Riwayat pengukuran tidak menunjukkan penurunan pada kunjungan terakhir.';

        if ($weightDelta < -0.3 || $heightDelta < 0) {
            $risk = 'tinggi';
            $label = 'Tren perlu ditinjau bidan';
            $message = 'Kunjungan terakhir menunjukkan penurunan BB atau TB. Perlu validasi pengukuran dan peninjauan bidan.';
        } elseif ($weightDelta < 0 || $heightDelta == 0.0 || $heightVelocity < 0.3) {
            $risk = 'sedang';
            $label = 'Tren perlu perhatian';
            $message = 'Pertumbuhan terakhir melambat. Pantau ulang dan beri edukasi sebelum jadwal berikutnya.';
        }

        return [
            'risk_level' => $risk,
            'label' => $label,
            'data_points' => $rows->count(),
            'weight_delta_kg' => $weightDelta,
            'height_delta_cm' => $heightDelta,
            'height_velocity_cm_per_month' => $heightVelocity,
            'message' => $message,
        ];
    }

    private function highestRisk(?string $modelRisk, ?string $trendRisk): string
    {
        $rank = ['rendah' => 0, 'sedang' => 1, 'tinggi' => 2];
        $model = $rank[$modelRisk ?? 'rendah'] ?? 0;
        $trend = $rank[$trendRisk ?? 'rendah'] ?? 0;
        return array_search(max($model, $trend), $rank, true) ?: 'rendah';
    }

    private function requireBidan(Request $request): ?JsonResponse
    {
        return $request->user()->role === 'bidan'
            ? null
            : response()->json(['message' => 'Akses ditolak.'], 403);
    }

    private function checkBalitaAccess(Request $request, object $balita): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }
        abort_if($balita->posyandu_id !== $request->user()->posyandu_id, 403, 'Akses ditolak.');
    }

    private function requireBidanOrAdmin(Request $request): ?JsonResponse
    {
        return in_array($request->user()->role, ['bidan', 'admin'], true)
            ? null
            : response()->json(['message' => 'Akses ditolak.'], 403);
    }

    private function requireBidanOrKader(Request $request): ?JsonResponse
    {
        return in_array($request->user()->role, ['bidan', 'kader'], true)
            ? null
            : response()->json(['message' => 'Akses ditolak.'], 403);
    }

    private function requireAdmin(Request $request): ?JsonResponse
    {
        return $request->user()->role === 'admin'
            ? null
            : response()->json(['message' => 'Akses ditolak.'], 403);
    }

    private function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'nik_nip' => $user->nik_nip,
            'role' => $user->role,
            'posyandu_id' => $user->posyandu_id,
            'status' => $user->status,
        ];
    }

    private function withTimestamps(array $data): array
    {
        return $data + ['created_at' => now(), 'updated_at' => now()];
    }

    private function touch(array $data): array
    {
        return $data + ['updated_at' => now()];
    }

    private function row(string $table, int $id): ?object
    {
        return DB::table($table)->where('id', $id)->first();
    }

    private function rowOrFail(string $table, int $id): object
    {
        $row = $this->row($table, $id);
        abort_if(! $row, 404);

        return $row;
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    private function paginated($paginator, ?callable $mapper = null): array
    {
        return [
            'data' => $mapper ? array_map($mapper, $paginator->items()) : $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function kelompokUsia(int $umurBulan): int
    {
        return match (true) {
            $umurBulan <= 5 => 0,
            $umurBulan <= 11 => 1,
            $umurBulan <= 23 => 2,
            $umurBulan <= 35 => 3,
            default => 4,
        };
    }

    private function kategoriPenghasilan(int $penghasilan): int
    {
        // ponytail: keep the training-time 1/2/3 scale; update if the model metadata changes.
        $umkTangerang = 5210377;

        return match (true) {
            $penghasilan < $umkTangerang => 1,
            $penghasilan === $umkTangerang => 2,
            default => 3,
        };
    }

    private function riskFromClass(int $class): string
    {
        return match ($class) {
            1 => 'sedang',
            2 => 'tinggi',
            default => 'rendah',
        };
    }

    private function riskText(?string $risk): string
    {
        return match ($risk) {
            'sedang' => 'Perlu perhatian',
            'tinggi' => 'Perlu ditinjau bidan',
            default => 'Risiko rendah',
        };
    }

    private function posyanduPayload(Request $request): array
    {
        return $request->validate([
            'nama_posyandu' => ['required', 'string'],
            'alamat' => ['nullable', 'string'],
            'desa' => ['nullable', 'string'],
            'kecamatan' => ['nullable', 'string'],
            'bidan_id' => ['nullable', 'exists:users,id'],
        ]);
    }

    private function notificationPayload(object $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'judul' => $row->judul,
            'pesan' => $row->pesan,
            'tipe' => $row->tipe,
            'data' => json_decode($row->data_json ?? '{}', true) ?: [],
            'is_read' => (bool) $row->is_read,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    private function notify(int $userId, string $judul, string $pesan, string $tipe, array $data = []): void
    {
        DB::table('notifikasi')->insert($this->withTimestamps([
            'user_id' => $userId,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => $tipe,
            'data_json' => json_encode($data),
            'is_read' => false,
        ]));

        $this->sendFcmNotification($userId, $judul, $pesan, $tipe, $data);
    }

    private function sendFcmNotification(int $userId, string $judul, string $pesan, string $tipe, array $data): void
    {
        $path = (string) config('services.fcm.service_account');
        $token = User::query()->whereKey($userId)->value('fcm_token');
        if (! $token || ! file_exists($path)) {
            return;
        }

        try {
            $accessToken = $this->getGoogleAccessToken();
            if (!$accessToken) {
                return;
            }

            $json = json_decode(file_get_contents($path), true);
            $projectId = $json['project_id'];

            // Note: FCM v1 API requires values in 'data' to be strings.
            $stringifiedData = collect(array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK', 'type' => $tipe]))
                ->map(fn($value) => (string) $value)
                ->toArray();

            Http::withToken($accessToken)
                ->timeout((int) config('services.fcm.timeout', 5))
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $judul,
                            'body' => $pesan,
                        ],
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'channel_id' => 'posyandu_alerts',
                                'sound' => 'default',
                            ],
                        ],
                        'data' => $stringifiedData,
                    ]
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function getGoogleAccessToken(): ?string
    {
        $path = (string) config('services.fcm.service_account');
        if (! file_exists($path)) {
            return null;
        }

        $json = json_decode(file_get_contents($path), true);
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $json['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlEncode = function($data) {
            return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
        };

        $base64Header = $base64UrlEncode($header);
        $base64Payload = $base64UrlEncode($payload);

        openssl_sign($base64Header . "." . $base64Payload, $signature, $json['private_key'], "sha256WithRSAEncryption");
        $jwt = $base64Header . "." . $base64Payload . "." . $base64UrlEncode($signature);

        $response = Http::asForm()->post($json['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);

        return $response->json('access_token');
    }
}

