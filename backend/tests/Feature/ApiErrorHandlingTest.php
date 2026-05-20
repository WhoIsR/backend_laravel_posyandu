<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    public function test_api_database_error_returns_safe_message(): void
    {
        config([
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 1,
        ]);

        $this->postJson('/api/login', [
            'nik_nip' => '3271010101010001',
            'password' => 'password',
        ])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Database belum siap. Pastikan MySQL/MariaDB berjalan lalu coba lagi.'
            )
            ->assertJsonMissing(['message' => 'SQLSTATE']);
    }
}
