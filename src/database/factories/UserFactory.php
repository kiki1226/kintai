<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(function (array $attrs) {
            $state = [];
            if (Schema::hasColumn('users', 'is_admin')) {
                $state['is_admin'] = true;
            }
            if (Schema::hasColumn('users', 'role')) {
                $state['role'] = 'admin';
            }
            return $state;
        });
    }

    public function staff(): static
    {
        return $this->state(function (array $attrs) {
            $state = [];
            if (Schema::hasColumn('users', 'is_admin')) {
                $state['is_admin'] = false;
            }
            if (Schema::hasColumn('users', 'role')) {
                $state['role'] = 'staff';
            }
            return $state;
        });
    }
}
