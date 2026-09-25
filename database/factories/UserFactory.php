<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Strategist,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function strategist(): static
    {
        return $this->state(fn () => ['role' => UserRole::Strategist]);
    }

    public function lgaLeader(int $lgaId): static
    {
        return $this->state(fn () => ['role' => UserRole::LgaLeader, 'lga_id' => $lgaId]);
    }

    public function coordinator(Ward $ward): static
    {
        return $this->state(fn () => ['role' => UserRole::WardCoordinator, 'ward_id' => $ward->id, 'lga_id' => $ward->lga_id]);
    }

    /**
     * A field agent with a fake phone number (never a real one) and PIN 1234.
     */
    public function agent(Ward $ward): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Agent,
            'ward_id' => $ward->id,
            'lga_id' => $ward->lga_id,
            'email' => null,
            'phone' => '+23480'.fake()->unique()->numerify('00######'),
            'password' => Hash::make('1234'),
        ]);
    }
}
