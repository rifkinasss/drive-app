<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TrashStorageService
{
    public function stage(File $file): ?array
    {
        if ($file->path === null) {
            return null;
        }
        $storage = $this->disk($file);
        if (! $storage->exists($file->path)) {
            Log::warning('Trashed file metadata has no physical object during deletion.', ['file_uuid' => $file->uuid]);

            return null;
        }
        $stagedPath = 'tmp-delete-pending/'.str()->uuid();
        try {
            if (! $storage->move($file->path, $stagedPath)) {
                throw new RuntimeException('Unable to stage deleted file.');
            }
        } catch (Throwable $exception) {
            throw new HttpException(507, 'Storage cleanup is unavailable.', $exception);
        }

        return ['disk' => $file->disk ?: config('cloud.disk'), 'original' => $file->path, 'staged' => $stagedPath];
    }

    public function remove(?array $staged): void
    {
        if ($staged === null) {
            return;
        }
        try {
            $storage = Storage::disk($staged['disk']);
            $committed = 'tmp-delete-committed/'.basename($staged['staged']);
            if ($storage->exists($staged['staged']) && ! $storage->move($staged['staged'], $committed)) {
                throw new RuntimeException('Unable to mark deletion staging as committed.');
            }
            $storage->delete($committed);
        } catch (Throwable $exception) {
            Log::error('Unable to remove staged deleted file.', ['file_uuid_path' => $staged['original'], 'exception' => $exception]);
        }
    }

    public function restore(?array $staged): void
    {
        if ($staged === null) {
            return;
        }
        try {
            $storage = Storage::disk($staged['disk']);
            if ($storage->exists($staged['staged'])) {
                $storage->move($staged['staged'], $staged['original']);
            }
        } catch (Throwable $exception) {
            Log::error('Unable to restore staged deleted file.', ['file_uuid_path' => $staged['original'], 'exception' => $exception]);
        }
    }

    private function disk(File $file): FilesystemAdapter
    {
        try {
            return Storage::disk($file->disk ?: config('cloud.disk'));
        } catch (Throwable $exception) {
            throw new HttpException(507, 'Storage cleanup is unavailable.', $exception);
        }
    }
}
