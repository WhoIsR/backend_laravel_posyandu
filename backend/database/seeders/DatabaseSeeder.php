<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $bidanId = User::query()->create([
            'nama' => 'Bidan Desa Melati',
            'nik_nip' => '197801012006042001',
            'password' => Hash::make('password'),
            'role' => 'bidan',
            'posyandu_id' => 1,
            'status' => 'aktif',
        ])->id;

        DB::table('posyandu')->insert([
            'id' => 1,
            'nama_posyandu' => 'Posyandu Melati 03',
            'alamat' => 'Balai Desa Melati',
            'desa' => 'Melati',
            'kecamatan' => 'Sukamaju',
            'bidan_id' => $bidanId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::query()->create([
            'nama' => 'Kader Melati',
            'nik_nip' => '3271010101010001',
            'password' => Hash::make('password'),
            'role' => 'kader',
            'posyandu_id' => 1,
            'status' => 'aktif',
        ]);

        DB::table('balita')->insert([
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
}
