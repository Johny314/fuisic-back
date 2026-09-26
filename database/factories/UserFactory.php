<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'user_type' => UserType::student->value,
        ];
    }

    public function admin(): static
    {
        return $this->withRole(RoleName::admin);
    }

    public function teacher(): static
    {
        return $this->withRole(RoleName::teacher);
    }

    public function moderator(): static
    {
        return $this->withRole(RoleName::moderator);
    }

    public function parent(): static
    {
        return $this->withRole(RoleName::parent);
    }

    /** Аккаунт ребёнка: без email, вход по логину; $parent — создавший его родитель. */
    public function child(?User $parent = null): static
    {
        return $this
            ->state(fn () => [
                'email' => null,
                'email_verified_at' => null,
                // всегда в пределах App\Rules\Username (3–32 символа): slug бывал длиннее 32
                'username' => 'child_'.$this->faker->unique()->numerify('########'),
                'grade' => $this->faker->numberBetween(1, 11),
                'created_by_id' => $parent?->id,
            ])
            ->afterCreating(fn (User $child) => $parent?->children()->attach($child));
    }

    public function withRole(RoleName $role): static
    {
        return $this
            ->state(['user_type' => $role->legacyUserType()->value])
            ->afterCreating(fn (User $user) => $user->syncRoles([$role->value]));
    }
}
