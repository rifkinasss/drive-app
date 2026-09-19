<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Folder> */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'trashed_at' => null,
            'is_starred' => false,
        ];
    }

    public function trashed(): static
    {
        return $this->state(fn (): array => ['trashed_at' => now()]);
    }
}
