<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'nik_nip' => fake()->unique()->numerify('################'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['kader', 'bidan']),
            'posyandu_id' => null,
            'fcm_token' => null,
            'status' => 'aktif',
        ];
    }
}
