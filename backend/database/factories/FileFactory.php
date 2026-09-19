<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<File> */
class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'owner_id' => User::factory(),
            'folder_id' => null,
            'original_name' => fake()->unique()->word().'.pdf',
            'stored_name' => $uuid.'.bin',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1, 10_000_000),
            'checksum' => null,
            'is_starred' => false,
            'trashed_at' => null,
        ];
    }

    public function inFolder(?Folder $folder = null): static
    {
        return $this->state(fn (): array => ['folder_id' => $folder?->getKey() ?? Folder::factory()]);
    }

    public function starred(): static
    {
        return $this->state(['is_starred' => true]);
    }

    public function trashed(): static
    {
        return $this->state(['trashed_at' => now()]);
    }

    public function image(): static
    {
        return $this->state([
            'original_name' => fake()->unique()->word().'.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
