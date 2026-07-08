<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $password = Hash::make('password');

        foreach ([
            'analytics_events',
            'notifikasi',
            'distribusi_pmt',
            'validasi_medis',
            'rujukan',
            'hasil_prediksi',
            'pengukuran',
            'sesi_posyandu',
            'jadwal_posyandu',
            'katalog_pmt',
            'balita',
            'posyandu',
            'users',
        ] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        $adminId = User::query()->create([
            'nama' => 'Admin Posyandu',
            'nik_nip' => '199001012020011001',
            'password' => $password,
            'role' => 'admin',
            'posyandu_id' => null,
            'status' => 'aktif',
        ])->id;

        $bidanIds = [];
        foreach ([
            ['Bidan Ratna Wulandari', '197801012006042001', 1],
            ['Bidan Rina Kurniasih', '197902022007042002', 2],
            ['Bidan Maya Lestari', '198003032008042003', 3],
        ] as [$nama, $nik, $posyanduId]) {
            $bidanIds[$posyanduId] = User::query()->create([
                'nama' => $nama,
                'nik_nip' => $nik,
                'password' => $password,
                'role' => 'bidan',
                'posyandu_id' => $posyanduId,
                'status' => 'aktif',
            ])->id;
        }

        $kaderIds = [];
        foreach ([
            ['Kader Sri Melati', '3271010101010001', 1],
            ['Kader Dewi Sari', '3271010101010002', 1],
            ['Kader Nur Anggraeni', '3271010101010003', 2],
            ['Kader Lilis Mawarni', '3271010101010004', 3],
        ] as [$nama, $nik, $posyanduId]) {
            $kaderIds[$posyanduId][] = User::query()->create([
                'nama' => $nama,
                'nik_nip' => $nik,
                'password' => $password,
                'role' => 'kader',
                'posyandu_id' => $posyanduId,
                'status' => 'aktif',
            ])->id;
        }

        foreach ([
            [1, 'Posyandu Melati 03', 'Balai Desa Melati', 'Melati', 'Sukamaju'],
            [2, 'Posyandu Anggrek 01', 'Balai RW 01 Anggrek', 'Anggrek', 'Sukamaju'],
            [3, 'Posyandu Mawar 02', 'PAUD Mawar', 'Mawar', 'Sukamaju'],
        ] as [$id, $nama, $alamat, $desa, $kecamatan]) {
            DB::table('posyandu')->insert([
                'id' => $id,
                'nama_posyandu' => $nama,
                'alamat' => $alamat,
                'desa' => $desa,
                'kecamatan' => $kecamatan,
                'bidan_id' => $bidanIds[$id],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('katalog_pmt')->insert([
            $this->pmt('Biskuit Balita', 'makanan tambahan', 'paket', 42, 12),
            $this->pmt('Susu UHT Anak', 'minuman tambahan', 'kotak', 28, 10),
            $this->pmt('Telur Ayam', 'protein', 'butir', 90, 30),
            $this->pmt('Kacang Hijau', 'bahan pangan', 'kg', 16, 8),
        ]);

        $childNames = [
            'Raka Aditya', 'Alya Safira', 'Naya Kirana', 'Bima Farrel', 'Salsa Nabila',
            'Dimas Arkan', 'Kinan Maharani', 'Adit Ramadhan', 'Laras Anindya', 'Rafi Alvaro',
            'Mika Prameswari', 'Farhan Akbar', 'Naura Azzahra', 'Gilang Pratama', 'Keisha Amalia',
            'Rania Putri', 'Bagas Saputra', 'Zahra Lestari', 'Fathan Rizqi', 'Aurel Cahyani',
        ];
        $familyNames = ['Wijaya', 'Prasetyo', 'Hidayat', 'Saputra', 'Lestari'];
        $genders = ['L', 'P', 'P', 'L', 'P', 'L', 'P', 'L', 'P', 'L', 'P', 'L', 'P', 'L', 'P', 'P', 'L', 'P', 'L', 'P'];
        $mothers = [
            'Wulan Handayani', 'Siti Aminah', 'Rina Marlina', 'Dewi Kartika', 'Ayu Permatasari',
            'Maya Lestari', 'Novi Rahmawati', 'Fitri Nurlaila', 'Ratna Wulandari', 'Yuni Astuti',
            'Ika Puspitasari', 'Marlina Suryani', 'Desi Anggraini', 'Tari Andriani', 'Nurhayati',
        ];
        for ($i = 1; $i <= 100; $i++) {
            $posyanduId = (($i - 1) % 3) + 1;
            $baseName = $childNames[($i - 1) % count($childNames)];
            $familyName = $familyNames[intdiv($i - 1, count($childNames)) % count($familyNames)];
            DB::table('balita')->insert([
                'posyandu_id' => $posyanduId,
                'nama_balita' => $baseName.' '.$familyName,
                'nik_balita' => '3174'.str_pad((string) $i, 12, '0', STR_PAD_LEFT),
                'tanggal_lahir' => now()->subMonths(8 + ($i % 48))->subDays($i % 21)->toDateString(),
                'jenis_kelamin' => $genders[($i - 1) % count($genders)],
                'nama_ibu' => $mothers[($i - 1) % count($mothers)],
                'nik_ibu' => '3271'.str_pad((string) ($i + 500), 12, '0', STR_PAD_LEFT),
                'alamat' => 'RT '.(($i % 8) + 1).' RW '.(($i % 4) + 1).' Desa '.$this->desa($posyanduId),
                'penghasilan' => 1200000 + (($i % 9) * 350000),
                'jumlah_keluarga' => 3 + ($i % 5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sessionIds = [];
        for ($monthIndex = 0; $monthIndex < 6; $monthIndex++) {
            $date = now()->subMonths(5 - $monthIndex)->startOfMonth()->addDays(9)->toDateString();
            foreach ([1, 2, 3] as $posyanduId) {
                $jadwalId = DB::table('jadwal_posyandu')->insertGetId([
                    'posyandu_id' => $posyanduId,
                    'tanggal' => $date,
                    'jam_mulai' => '08:00',
                    'jam_selesai' => '11:00',
                    'lokasi' => 'Balai '.$this->desa($posyanduId),
                    'keterangan' => 'Sesi timbang dan ukur bulanan.',
                    'notif_h1_sent' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sessionIds[] = DB::table('sesi_posyandu')->insertGetId([
                    'jadwal_posyandu_id' => $jadwalId,
                    'posyandu_id' => $posyanduId,
                    'tanggal' => $date,
                    'status' => $monthIndex === 5 ? 'berjalan' : 'selesai',
                    'dibuka_oleh' => $kaderIds[$posyanduId][0],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $measurementNo = 0;
        $historyCountByChild = [];
        foreach ($sessionIds as $index => $sessionId) {
            $session = DB::table('sesi_posyandu')->where('id', $sessionId)->first();
            $children = DB::table('balita')
                ->where('posyandu_id', $session->posyandu_id)
                ->orderBy('id')
                ->limit(16)
                ->get();
            foreach ($children as $child) {
                $measurementNo++;
                $visitNumber = $historyCountByChild[$child->id] ?? 0;
                $historyCountByChild[$child->id] = $visitNumber + 1;
                $heightGrowth = $visitNumber * 0.7;
                $weightGrowth = $visitNumber * 0.25;

                if ($child->id % 13 === 0 && $visitNumber === 5) {
                    $weightGrowth -= 1.0;
                }
                if ($child->id % 11 === 0 && $visitNumber === 5) {
                    $heightGrowth -= 1.0;
                }

                $height = round(68 + ($child->id % 18) + $heightGrowth, 1);
                $weight = round(7.2 + (($child->id % 10) * 0.35) + $weightGrowth, 2);
                $risk = $measurementNo % 7 === 0 ? 'tinggi' : ($measurementNo % 4 === 0 ? 'sedang' : 'rendah');
                $pengukuranId = DB::table('pengukuran')->insertGetId([
                    'sesi_posyandu_id' => $sessionId,
                    'balita_id' => $child->id,
                    'kader_id' => $kaderIds[$session->posyandu_id][0],
                    'tanggal_ukur' => $session->tanggal,
                    'berat_badan' => $weight,
                    'tinggi_badan' => $height,
                    'status_prediksi' => 'selesai',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $prediksiId = DB::table('hasil_prediksi')->insertGetId([
                    'pengukuran_id' => $pengukuranId,
                    'umur_bulan' => 24 + ($child->id % 30),
                    'jenis_kelamin_encoded' => $child->jenis_kelamin === 'L' ? 1 : 0,
                    'tinggi_badan' => $height,
                    'kelompok_usia' => 3,
                    'tb_per_bulan' => round($height / (25 + ($child->id % 30)), 4),
                    'penghasilan' => $child->penghasilan,
                    'jumlah_keluarga' => $child->jumlah_keluarga,
                    'predicted_class' => $risk === 'tinggi' ? 2 : ($risk === 'sedang' ? 1 : 0),
                    'risk_level' => $risk,
                    'risk_score' => $risk === 'rendah' ? 0.18 : ($risk === 'sedang' ? 0.62 : 0.78),
                    'probability_json' => json_encode($this->probability($risk)),
                    'model_version' => 'xgboost_v1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($risk !== 'rendah') {
                    $rujukanId = DB::table('rujukan')->insertGetId([
                        'balita_id' => $child->id,
                        'pengukuran_id' => $pengukuranId,
                        'hasil_prediksi_id' => $prediksiId,
                        'status_rujukan' => $measurementNo % 5 === 0 ? 'divalidasi' : 'menunggu_validasi',
                        'tanggal_rujukan' => $session->tanggal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('notifikasi')->insert($this->notification(
                        $bidanIds[$session->posyandu_id],
                        'Rujukan masuk',
                        'Ada hasil skrining yang perlu ditinjau.',
                        'rujukan_masuk',
                        ['rujukan_id' => $rujukanId]
                    ));

                    if ($measurementNo % 5 === 0) {
                        $validationId = DB::table('validasi_medis')->insertGetId([
                            'rujukan_id' => $rujukanId,
                            'bidan_id' => $bidanIds[$session->posyandu_id],
                            'keputusan' => $measurementNo % 10 === 0 ? 'pmt' : 'observasi',
                            'catatan_bidan' => 'Pantau ulang dan beri edukasi keluarga.',
                            'tanggal_validasi' => $session->tanggal,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        DB::table('notifikasi')->insert($this->notification(
                            $kaderIds[$session->posyandu_id][0],
                            'Validasi selesai',
                            'Rujukan sudah ditinjau bidan.',
                            'validasi_selesai',
                            ['rujukan_id' => $rujukanId]
                        ));
                        if ($measurementNo % 10 === 0) {
                            DB::table('distribusi_pmt')->insert([
                                'validasi_medis_id' => $validationId,
                                'balita_id' => $child->id,
                                'pmt_id' => 1,
                                'bidan_id' => $bidanIds[$session->posyandu_id],
                                'jumlah' => 1,
                                'tanggal_distribusi' => $session->tanggal,
                                'keterangan' => 'Paket PMT demo.',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        DB::table('notifikasi')->insert($this->notification(
            $adminId,
            'Data demo siap',
            'Seeder besar Posyandu ML sudah tersedia untuk presentasi.',
            'system',
            []
        ));

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    }

    private function pmt(string $name, string $type, string $unit, int $stock, int $minimum): array
    {
        return [
            'nama_barang' => $name,
            'jenis_barang' => $type,
            'satuan' => $unit,
            'stok_saat_ini' => $stock,
            'stok_minimum' => $minimum,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function desa(int $posyanduId): string
    {
        return match ($posyanduId) {
            2 => 'Anggrek',
            3 => 'Mawar',
            default => 'Melati',
        };
    }

    private function probability(string $risk): array
    {
        return match ($risk) {
            'tinggi' => ['rendah' => 0.08, 'sedang' => 0.14, 'tinggi' => 0.78],
            'sedang' => ['rendah' => 0.18, 'sedang' => 0.62, 'tinggi' => 0.20],
            default => ['rendah' => 0.82, 'sedang' => 0.13, 'tinggi' => 0.05],
        };
    }

    private function notification(int $userId, string $title, string $message, string $type, array $data): array
    {
        return [
            'user_id' => $userId,
            'judul' => $title,
            'pesan' => $message,
            'tipe' => $type,
            'data_json' => json_encode($data),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
