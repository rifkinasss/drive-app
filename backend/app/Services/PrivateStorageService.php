<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PrivateStorageService
{
    public function disk(?string $name = null): FilesystemAdapter
    {
        try {
            return Storage::disk($name ?: config('cloud.disk'));
        } catch (Throwable $exception) {
            throw new HttpException(507, 'Storage is unavailable.', $exception);
        }
    }

    public function stageUpload(FilesystemAdapter $storage, UploadedFile $upload, string $tempPath): void
    {
        try {
            if ($storage->putFileAs('tmp', $upload, basename($tempPath)) === false) {
                throw new RuntimeException('Unable to stage the uploaded file.');
            }
        } catch (Throwable $exception) {
            $this->cleanup($storage, [$tempPath]);
            throw new HttpException(507, 'Storage is unavailable.', $exception);
        }
    }

    public function moveOrFail(FilesystemAdapter $storage, string $from, string $to): void
    {
        try {
            if (! $storage->move($from, $to)) {
                throw new RuntimeException('Unable to move the uploaded file.');
            }
        } catch (Throwable $exception) {
            throw new HttpException(507, 'Storage is unavailable.', $exception);
        }
    }

    public function cleanup(FilesystemAdapter $storage, array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if ($path !== null && $storage->exists($path)) {
                try {
                    $storage->delete($path);
                } catch (Throwable $exception) {
                    Log::error('Unable to clean up private storage object.', ['path' => $path, 'exception' => $exception]);
                }
            }
        }
    }
}
