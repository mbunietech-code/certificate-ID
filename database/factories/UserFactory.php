<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\School;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
            'role_id' => fn () => Role::where('slug', Role::VIEWER)->value('id'),
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

    /** Give the user a system role (roles must be seeded, see RolePermissionSeeder). */
    public function role(string $slug): static
    {
        return $this->state(fn () => ['role_id' => Role::where('slug', $slug)->value('id')]);
    }

    public function superAdmin(): static
    {
        return $this->role(Role::SUPER_ADMIN)->state(['school_id' => null]);
    }

    public function forSchool(School $school, string $role = Role::SCHOOL_ADMIN): static
    {
        return $this->role($role)->state(['school_id' => $school->id]);
    }
}
