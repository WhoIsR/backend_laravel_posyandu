<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('posyandu')->insert([
            [
                'id' => 1,
                'nama_posyandu' => 'Posyandu Melati',
                'alamat' => 'Balai Melati',
                'desa' => 'Melati',
                'kecamatan' => 'Kronjo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nama_posyandu' => 'Posyandu Anggrek',
                'alamat' => 'Balai Anggrek',
                'desa' => 'Anggrek',
                'kecamatan' => 'Kronjo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_kader_cannot_create_or_move_balita_outside_assigned_posyandu(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/balita', $this->balitaPayload(2));

        $created->assertCreated()->assertJsonPath('posyandu_id', 1);

        $this->withToken($token)
            ->putJson('/api/balita/'.$created->json('id'), $this->balitaPayload(2) + ['nama_balita' => 'Anak Melati'])
            ->assertOk()
            ->assertJsonPath('posyandu_id', 1);
    }

    public function test_kader_cannot_use_session_or_balita_from_another_posyandu(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;
        $balitaId = DB::table('balita')->insertGetId($this->balitaPayload(2) + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sesiId = DB::table('sesi_posyandu')->insertGetId([
            'jadwal_posyandu_id' => null,
            'posyandu_id' => 2,
            'tanggal' => now()->toDateString(),
            'status' => 'berjalan',
            'dibuka_oleh' => $kader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($token)->postJson('/api/pengukuran', [
            'sesi_posyandu_id' => $sesiId,
            'balita_id' => $balitaId,
            'berat_badan' => 10.5,
            'tinggi_badan' => 82.0,
        ])->assertForbidden();

        $this->withToken($token)->getJson('/api/sesi/'.$sesiId.'/skrining')->assertForbidden();
        $this->withToken($token)->postJson('/api/sesi/'.$sesiId.'/selesai')->assertForbidden();
    }

    public function test_analytics_discards_sensitive_login_properties(): void
    {
        $this->postJson('/api/analytics', [
            'event_name' => 'login_started',
            'properties' => [
                'nik_nip' => '3271010101010001',
                'password' => 'rahasia',
                'role' => 'kader',
            ],
        ])->assertCreated()
            ->assertJsonMissingPath('properties.nik_nip')
            ->assertJsonMissingPath('properties.password')
            ->assertJsonPath('properties.role', 'kader');
    }

    public function test_operational_account_requires_posyandu_and_deactivation_revokes_existing_token(): void
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

        $this->withToken($adminToken)->postJson('/api/admin/users', [
            'nama' => 'Bidan Tanpa Posyandu',
            'nik_nip' => '197801012006042099',
            'password' => 'password',
            'role' => 'bidan',
            'posyandu_id' => null,
            'status' => 'aktif',
        ])->assertUnprocessable();

        $this->withToken($adminToken)->putJson('/api/admin/users/'.$kader->id, [
            'nama' => $kader->nama,
            'role' => 'kader',
            'posyandu_id' => 1,
            'status' => 'nonaktif',
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $kaderToken, 2)[1]),
        ]);
        $this->app['auth']->forgetGuards();
        $this->withToken($kaderToken)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_retry_is_only_allowed_for_failed_prediction(): void
    {
        $kader = $this->createUser('Kader Melati', '3271010101010001', 'kader', 1);
        $token = $kader->createToken('test')->plainTextToken;
        $balitaId = DB::table('balita')->insertGetId($this->balitaPayload(1) + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sesiId = DB::table('sesi_posyandu')->insertGetId([
            'jadwal_posyandu_id' => null,
            'posyandu_id' => 1,
            'tanggal' => now()->toDateString(),
            'status' => 'berjalan',
            'dibuka_oleh' => $kader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $measurementId = DB::table('pengukuran')->insertGetId([
            'sesi_posyandu_id' => $sesiId,
            'balita_id' => $balitaId,
            'kader_id' => $kader->id,
            'tanggal_ukur' => now()->toDateString(),
            'berat_badan' => 10.5,
            'tinggi_badan' => 82.0,
            'status_prediksi' => 'selesai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson('/api/pengukuran/'.$measurementId.'/retry-prediksi')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Prediksi hanya dapat dicoba lagi setelah status gagal.');
    }

    public function test_password_reset_revokes_existing_tokens(): void
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

        $this->withToken($adminToken)->putJson('/api/admin/users/'.$kader->id, [
            'nama' => $kader->nama,
            'role' => 'kader',
            'posyandu_id' => 1,
            'status' => 'aktif',
            'password' => 'password-baru',
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $kaderToken, 2)[1]),
        ]);
    }

    public function test_anonymous_analytics_rejects_oversized_properties(): void
    {
        $this->postJson('/api/analytics', [
            'event_name' => 'login_failed',
            'properties' => ['message' => str_repeat('x', 5000)],
        ])->assertUnprocessable();
    }

    private function createUser(string $name, string $nik, string $role, int $posyanduId): User
    {
        return User::query()->create([
            'nama' => $name,
            'nik_nip' => $nik,
            'password' => Hash::make('password'),
            'role' => $role,
            'posyandu_id' => $posyanduId,
            'status' => 'aktif',
        ]);
    }

    private function balitaPayload(int $posyanduId): array
    {
        return [
            'posyandu_id' => $posyanduId,
            'nama_balita' => 'Anak Uji',
            'nik_balita' => '3671000000000001',
            'tanggal_lahir' => now()->subMonths(24)->toDateString(),
            'jenis_kelamin' => 'P',
            'nama_ibu' => 'Ibu Uji',
            'nik_ibu' => '3671000000000099',
            'alamat' => 'Kecamatan Kronjo',
            'penghasilan' => 2500000,
            'jumlah_keluarga' => 4,
        ];
    }
}
