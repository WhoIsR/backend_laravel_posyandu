<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosyanduMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_kader_and_bidan_can_login_and_me_returns_role(): void
    {
        $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $this->createUser('Bidan Desa', '197801012006042001', 'bidan', 1);
        User::query()->create([
            'nama' => 'Admin Posyandu',
            'nik_nip' => '199001012020011001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'posyandu_id' => null,
            'status' => 'aktif',
        ]);

        $kaderLogin = $this->postJson('/api/login', [
            'nik_nip' => '3271010101010001',
            'password' => 'password',
        ]);

        $kaderLogin
            ->assertOk()
            ->assertJsonPath('user.role', 'kader')
            ->assertJsonStructure(['token', 'user' => ['id', 'nama', 'nik_nip', 'role']]);

        $this->withToken($kaderLogin->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('role', 'kader');

        $this->postJson('/api/login', [
            'nik_nip' => '3271010101010001',
            'password' => 'salah',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'nik_nip' => '199001012020011001',
            'password' => 'password',
        ])->assertOk()
          ->assertJsonPath('user.role', 'admin');
    }

    public function test_admin_can_manage_users_and_posyandu_but_other_roles_cannot(): void
    {
        $admin = User::query()->create([
            'nama' => 'Admin Posyandu',
            'nik_nip' => '199001012020011001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'posyandu_id' => null,
            'status' => 'aktif',
        ]);
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $adminToken = $admin->createToken('test')->plainTextToken;
        $kaderToken = $kader->createToken('test')->plainTextToken;

        $posyandu = $this->withToken($adminToken)->postJson('/api/admin/posyandu', [
            'nama_posyandu' => 'Posyandu Anggrek 01',
            'alamat' => 'Balai RW 01',
            'desa' => 'Anggrek',
            'kecamatan' => 'Sukamaju',
            'bidan_id' => null,
        ]);

        $posyandu->assertCreated()
            ->assertJsonPath('nama_posyandu', 'Posyandu Anggrek 01');

        $createdUser = $this->withToken($adminToken)->postJson('/api/admin/users', [
            'nama' => 'Bidan Anggrek',
            'nik_nip' => '198812122012042002',
            'password' => 'password',
            'role' => 'bidan',
            'posyandu_id' => $posyandu->json('id'),
            'status' => 'aktif',
        ]);

        $createdUser->assertCreated()
            ->assertJsonPath('role', 'bidan')
            ->assertJsonMissing(['password']);

        $this->withToken($adminToken)->putJson('/api/admin/users/'.$createdUser->json('id'), [
            'nama' => 'Bidan Anggrek',
            'role' => 'bidan',
            'posyandu_id' => $posyandu->json('id'),
            'status' => 'nonaktif',
        ])->assertOk()
          ->assertJsonPath('status', 'nonaktif');

        $this->assertNotSame($adminToken, $kaderToken);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/users')->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/posyandu', [
            'nama_posyandu' => 'Tidak Boleh',
        ])->assertForbidden();
    }

    public function test_kader_cannot_access_bidan_only_pmt_report_and_validation_endpoints(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/pmt')->assertForbidden();
        $this->withToken($token)->getJson('/api/laporan/prediksi')->assertForbidden();
        $this->withToken($token)->postJson('/api/rujukan/1/validasi', [
            'keputusan' => 'observasi',
            'catatan_bidan' => 'Pantau bulan depan.',
        ])->assertForbidden();
    }

    public function test_balita_crud_search_and_pagination_follow_prd_fields(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;

        $this->seedPosyandu();

        $created = $this->withToken($token)->postJson('/api/balita', [
            'nama_balita' => 'Raka Pratama',
            'nik_balita' => '3174000000000001',
            'tanggal_lahir' => now()->subMonths(31)->toDateString(),
            'jenis_kelamin' => 'L',
            'nama_ibu' => 'Wulan',
            'nik_ibu' => '3174000000000099',
            'alamat' => 'Dusun Melati',
            'penghasilan' => 2500000,
            'jumlah_keluarga' => 4,
            'posyandu_id' => 1,
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('nama_balita', 'Raka Pratama');

        $this->withToken($token)
            ->getJson('/api/balita?search=Wulan&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.nama_balita', 'Raka Pratama')
            ->assertJsonPath('meta.per_page', 10);

        $this->withToken($token)
            ->putJson('/api/balita/'.$created->json('id'), [
                'nama_ibu' => 'Wulan Sari',
                'alamat' => 'Dusun Melati RT 02',
                'penghasilan' => 2600000,
                'jumlah_keluarga' => 5,
                'posyandu_id' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('nama_ibu', 'Wulan Sari');
    }

    public function test_pengukuran_saves_ml_prediction_and_creates_rujukan_for_medium_or_high_risk(): void
    {
        Http::fake([
            '*/predict' => Http::response([
                'predicted_class' => 1,
                'risk_level' => 'sedang',
                'probability' => [
                    'rendah' => 0.18,
                    'sedang' => 0.62,
                    'tinggi' => 0.20,
                ],
                'model_version' => 'xgboost_v1',
            ], 200),
        ]);

        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $bidan = $this->createUser('Bidan Desa', '197801012006042001', 'bidan', 1);
        $token = $kader->createToken('test')->plainTextToken;
        $this->seedPosyandu($bidan->id);
        $balitaId = $this->seedBalita();

        $sesi = $this->withToken($token)->postJson('/api/sesi', [
            'jadwal_posyandu_id' => null,
            'posyandu_id' => 1,
            'tanggal' => now()->toDateString(),
        ])->assertCreated();

        $saved = $this->withToken($token)->postJson('/api/pengukuran', [
            'sesi_posyandu_id' => $sesi->json('id'),
            'balita_id' => $balitaId,
            'berat_badan' => 11.4,
            'tinggi_badan' => 87.5,
        ]);

        $saved
            ->assertCreated()
            ->assertJsonPath('status_prediksi', 'selesai')
            ->assertJsonPath('hasil_prediksi.risk_level', 'sedang')
            ->assertJsonPath('rujukan.status_rujukan', 'menunggu_validasi');

        $this->withToken($token)->postJson('/api/pengukuran', [
            'sesi_posyandu_id' => $sesi->json('id'),
            'balita_id' => $balitaId,
            'berat_badan' => 11.5,
            'tinggi_badan' => 87.7,
        ])->assertStatus(422)
          ->assertJsonPath('message', 'Balita ini sudah dicatat pada sesi hari ini.');

        Http::assertSent(fn ($request) => $request->url() === config('services.ml_api.url').'/predict'
            && array_keys($request['features']) === [
                'Umur (bulan)',
                'Jenis Kelamin Encoded',
                'Tinggi Badan (cm)',
                'Kelompok Usia',
                'TB per Bulan',
                'Penghasilan',
                'Jumlah Keluarga',
            ]);
    }

    public function test_prediction_failure_keeps_pengukuran_and_allows_retry(): void
    {
        Http::fake([
            '*/predict' => Http::sequence()
                ->push(['message' => 'unavailable'], 500)
                ->push([
                    'predicted_class' => 0,
                    'risk_level' => 'rendah',
                    'probability' => ['rendah' => 0.82, 'sedang' => 0.13, 'tinggi' => 0.05],
                    'model_version' => 'xgboost_v1',
                ], 200),
        ]);

        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;
        $this->seedPosyandu();
        $balitaId = $this->seedBalita();
        $sesiId = $this->seedSesi($kader->id);

        $saved = $this->withToken($token)->postJson('/api/pengukuran', [
            'sesi_posyandu_id' => $sesiId,
            'balita_id' => $balitaId,
            'berat_badan' => 11.4,
            'tinggi_badan' => 87.5,
        ]);

        $saved
            ->assertCreated()
            ->assertJsonPath('status_prediksi', 'gagal');

        $this->withToken($token)
            ->postJson('/api/pengukuran/'.$saved->json('id').'/retry-prediksi')
            ->assertOk()
            ->assertJsonPath('status_prediksi', 'selesai')
            ->assertJsonPath('hasil_prediksi.risk_level', 'rendah');
    }

    public function test_bidan_validates_rujukan_and_pmt_distribution_reduces_stock_and_notifies_kader(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $bidan = $this->createUser('Bidan Desa', '197801012006042001', 'bidan', 1);
        $token = $bidan->createToken('test')->plainTextToken;
        $this->seedPosyandu($bidan->id);
        $balitaId = $this->seedBalita();
        $pengukuranId = $this->seedPengukuran($kader->id, $balitaId);
        $prediksiId = $this->seedPrediksi($pengukuranId, 'tinggi');
        $rujukanId = $this->seedRujukan($balitaId, $pengukuranId, $prediksiId);

        $validation = $this->withToken($token)->postJson('/api/rujukan/'.$rujukanId.'/validasi', [
            'keputusan' => 'pmt',
            'catatan_bidan' => 'Berikan PMT dan pantau ulang bulan depan.',
        ]);

        $validation
            ->assertCreated()
            ->assertJsonPath('keputusan', 'pmt');

        $pmt = $this->withToken($token)->postJson('/api/pmt', [
            'nama_barang' => 'Biskuit Balita',
            'jenis_barang' => 'makanan',
            'satuan' => 'dus',
            'stok_saat_ini' => 10,
            'stok_minimum' => 3,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/distribusi-pmt', [
            'validasi_medis_id' => $validation->json('id'),
            'balita_id' => $balitaId,
            'pmt_id' => $pmt->json('id'),
            'jumlah' => 2,
            'tanggal_distribusi' => now()->toDateString(),
            'keterangan' => 'Untuk satu minggu.',
        ])
            ->assertCreated()
            ->assertJsonPath('jumlah', 2);

        $this->assertDatabaseHas('katalog_pmt', [
            'id' => $pmt->json('id'),
            'stok_saat_ini' => 8,
        ]);

        $this->assertDatabaseHas('notifikasi', [
            'user_id' => $kader->id,
            'tipe' => 'pmt_disetujui',
        ]);

    }

    public function test_notifications_expose_route_payload_and_can_be_marked_read(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        \DB::table('notifikasi')->insert([
            'user_id' => $kader->id,
            'judul' => 'PMT disetujui',
            'pesan' => 'PMT untuk balita sudah disetujui bidan.',
            'tipe' => 'pmt_disetujui',
            'data_json' => json_encode(['distribusi_pmt_id' => 7]),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readToken = $kader->createToken('read')->plainTextToken;
        $notifications = $this->getJson('/api/notifikasi', [
                'Authorization' => 'Bearer '.$readToken,
            ])
            ->assertOk();

        $notifications
            ->assertJsonPath('data.0.tipe', 'pmt_disetujui')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('data.0.data.distribusi_pmt_id', 7);

        $readToken2 = $kader->createToken('read-2')->plainTextToken;
        $this->postJson('/api/notifikasi/'.$notifications->json('data.0.id').'/read', [], [
                'Authorization' => 'Bearer '.$readToken2,
            ])
            ->assertOk()
            ->assertJsonPath('is_read', true);
    }

    public function test_bidan_can_download_three_required_pdf_reports(): void
    {
        $bidan = $this->createUser('Bidan Desa', '197801012006042001', 'bidan', 1);
        $admin = User::query()->create([
            'nama' => 'Admin Posyandu',
            'nik_nip' => '199001012020011001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'posyandu_id' => null,
            'status' => 'aktif',
        ]);
        $token = $bidan->createToken('test')->plainTextToken;
        $adminToken = $admin->createToken('test-admin')->plainTextToken;
        $this->seedPosyandu($bidan->id);
        $balitaId = $this->seedBalita();
        $pengukuranId = $this->seedPengukuran($bidan->id, $balitaId);
        $prediksiId = $this->seedPrediksi($pengukuranId, 'tinggi');
        $rujukanId = $this->seedRujukan($balitaId, $pengukuranId, $prediksiId);
        $validationId = \DB::table('validasi_medis')->insertGetId([
            'rujukan_id' => $rujukanId,
            'bidan_id' => $bidan->id,
            'keputusan' => 'pmt',
            'catatan_bidan' => 'Berikan PMT.',
            'tanggal_validasi' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pmtId = \DB::table('katalog_pmt')->insertGetId([
            'nama_barang' => 'Biskuit Balita',
            'jenis_barang' => 'makanan',
            'satuan' => 'paket',
            'stok_saat_ini' => 10,
            'stok_minimum' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('distribusi_pmt')->insert([
            'validasi_medis_id' => $validationId,
            'balita_id' => $balitaId,
            'pmt_id' => $pmtId,
            'bidan_id' => $bidan->id,
            'jumlah' => 1,
            'tanggal_distribusi' => now()->toDateString(),
            'keterangan' => 'Demo laporan.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['prediksi', 'kehadiran', 'distribusi-pmt'] as $report) {
            $this->withToken($token)
                ->get('/api/laporan/'.$report.'?start_date=2026-05-01&end_date=2026-05-31')
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('content-disposition');
        }

        $this->withToken($adminToken)
            ->get('/api/laporan/prediksi?start_date=2026-05-01&end_date=2026-05-31')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_demo_seeder_creates_realistic_large_dataset(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        $this->assertDatabaseHas('users', ['role' => 'admin']);
        $this->assertGreaterThanOrEqual(100, \DB::table('balita')->count());
        $this->assertGreaterThanOrEqual(6, \DB::table('sesi_posyandu')->count());
        $this->assertGreaterThanOrEqual(40, \DB::table('pengukuran')->count());
        $this->assertGreaterThanOrEqual(10, \DB::table('rujukan')->count());
        $this->assertGreaterThanOrEqual(3, \DB::table('katalog_pmt')->count());
        $this->assertGreaterThanOrEqual(10, \DB::table('notifikasi')->count());
    }

    private function createUser(string $nama, string $nikNip, string $role, int $posyanduId): User
    {
        return User::query()->create([
            'nama' => $nama,
            'nik_nip' => $nikNip,
            'password' => Hash::make('password'),
            'role' => $role,
            'posyandu_id' => $posyanduId,
            'status' => 'aktif',
        ]);
    }

    private function seedPosyandu(?int $bidanId = null): void
    {
        \DB::table('posyandu')->insert([
            'id' => 1,
            'nama_posyandu' => 'Posyandu Melati 03',
            'alamat' => 'Balai Desa',
            'desa' => 'Melati',
            'kecamatan' => 'Sukamaju',
            'bidan_id' => $bidanId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBalita(): int
    {
        return \DB::table('balita')->insertGetId([
            'posyandu_id' => 1,
            'nama_balita' => 'Raka Pratama',
            'nik_balita' => '3174000000000001',
            'tanggal_lahir' => now()->subMonths(31)->toDateString(),
            'jenis_kelamin' => 'L',
            'nama_ibu' => 'Wulan',
            'nik_ibu' => '3174000000000099',
            'alamat' => 'Dusun Melati',
            'penghasilan' => 2500000,
            'jumlah_keluarga' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedSesi(int $kaderId): int
    {
        return \DB::table('sesi_posyandu')->insertGetId([
            'jadwal_posyandu_id' => null,
            'posyandu_id' => 1,
            'tanggal' => now()->toDateString(),
            'status' => 'berjalan',
            'dibuka_oleh' => $kaderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPengukuran(int $kaderId, int $balitaId): int
    {
        $sesiId = $this->seedSesi($kaderId);

        return \DB::table('pengukuran')->insertGetId([
            'sesi_posyandu_id' => $sesiId,
            'balita_id' => $balitaId,
            'kader_id' => $kaderId,
            'tanggal_ukur' => now()->toDateString(),
            'berat_badan' => 11.4,
            'tinggi_badan' => 87.5,
            'status_prediksi' => 'selesai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPrediksi(int $pengukuranId, string $riskLevel): int
    {
        return \DB::table('hasil_prediksi')->insertGetId([
            'pengukuran_id' => $pengukuranId,
            'umur_bulan' => 31,
            'jenis_kelamin_encoded' => 1,
            'tinggi_badan' => 87.5,
            'kelompok_usia' => 3,
            'tb_per_bulan' => 2.73,
            'penghasilan' => 2500000,
            'jumlah_keluarga' => 4,
            'predicted_class' => $riskLevel === 'tinggi' ? 2 : 1,
            'risk_level' => $riskLevel,
            'risk_score' => 0.72,
            'probability_json' => json_encode(['rendah' => 0.1, 'sedang' => 0.18, 'tinggi' => 0.72]),
            'model_version' => 'xgboost_v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedRujukan(int $balitaId, int $pengukuranId, int $prediksiId): int
    {
        return \DB::table('rujukan')->insertGetId([
            'balita_id' => $balitaId,
            'pengukuran_id' => $pengukuranId,
            'hasil_prediksi_id' => $prediksiId,
            'status_rujukan' => 'menunggu_validasi',
            'tanggal_rujukan' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
