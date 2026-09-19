<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Exceptions\FileNameConflictException;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class UploadFileService
{
    public function __construct(private readonly StorageQuotaService $quota, private readonly ActivityRecorder $activities, private readonly SystemSettingService $settings, private readonly StorageNotificationService $storageNotifications) {}

    public function upload(User $owner, UploadedFile $upload, ?string $folderUuid, ?string $strategy): File
    {
        $folder = $this->resolveFolder($owner, $folderUuid);
        $name = $this->validatedName($upload->getClientOriginalName());
        $extension = $this->extensionFrom($name);
        $this->assertExtensionAllowed($extension);

        $size = $upload->getSize();
        if (! is_int($size) || $size < 0) {
            throw ValidationException::withMessages(['file' => 'The uploaded file size is invalid.']);
        }

        $mimeType = $upload->getMimeType() ?: 'application/octet-stream';
        $checksum = hash_file('sha256', $upload->getRealPath());
        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate the uploaded file checksum.');
        }

        $existing = $this->findActiveDuplicate($owner, $folder?->getKey(), $name);
        $strategy ??= 'ask';

        if ($existing !== null && $strategy === 'ask') {
            throw new FileNameConflictException($existing);
        }

        if ($existing !== null && $strategy === 'keep_both') {
            $name = $this->uniqueName($owner, $folder?->getKey(), $name);
            $extension = $this->extensionFrom($name);
            $existing = null;
        }

        return $existing === null
            ? $this->createNew($owner, $folder, $upload, $name, $extension, $mimeType, $size, $checksum)
            : $this->replaceExisting($owner, $existing, $upload, $name, $extension, $mimeType, $size, $checksum);
    }

    private function createNew(User $owner, ?Folder $folder, UploadedFile $upload, string $name, ?string $extension, string $mimeType, int $size, string $checksum): File
    {
        $uuid = (string) str()->uuid();
        $diskName = config('cloud.disk');
        $storage = Storage::disk($diskName);
        $tempPath = 'tmp/'.$uuid;
        $finalPath = $this->finalPath($owner, $uuid);
        $this->stage($storage, $upload, $tempPath);

        try {
            $this->moveOrFail($storage, $tempPath, $finalPath);

            $file = $this->quota->transaction($owner, function (User $lockedOwner) use ($folder, $name, $uuid, $diskName, $finalPath, $extension, $mimeType, $size, $checksum): File {
                try {
                    $this->quota->assertCanStoreLocked($lockedOwner, $size);

                    $file = File::create([
                        'uuid' => $uuid,
                        'owner_id' => $lockedOwner->getKey(),
                        'folder_id' => $folder?->getKey(),
                        'original_name' => $name,
                        'stored_name' => $uuid,
                        'disk' => $diskName,
                        'path' => $finalPath,
                        'extension' => $extension,
                        'mime_type' => $mimeType,
                        'size_bytes' => $size,
                        'checksum' => $checksum,
                        'is_starred' => false,
                    ]);
                    $this->quota->applyDeltaLocked($lockedOwner, $size);
                    $this->activities->record($lockedOwner, ActivityAction::FileUploaded, $file, [
                        'sizeBytes' => $size,
                        'mimeType' => $mimeType,
                        'folderId' => $folder?->uuid,
                        'folderName' => $folder?->name,
                    ]);

                    return $file->load('folder');
                } catch (Throwable $exception) {
                    throw $this->translateDuplicate($exception, $lockedOwner, $folder?->getKey(), $name);
                }
            });
            $this->storageNotifications->evaluate($owner);

            return $file;
        } catch (Throwable $exception) {
            $this->cleanup($storage, [$tempPath, $finalPath]);
            throw $exception;
        }
    }

    private function replaceExisting(User $owner, File $existing, UploadedFile $upload, string $name, ?string $extension, string $mimeType, int $size, string $checksum): File
    {
        $uuid = (string) str()->uuid();
        $storage = Storage::disk($existing->disk ?: config('cloud.disk'));
        $tempPath = 'tmp/'.$uuid;
        $oldPath = $existing->path;
        $finalPath = $oldPath ?: $this->finalPath($owner, $existing->uuid);
        $backupPath = 'tmp/backup-'.$uuid;
        $this->stage($storage, $upload, $tempPath);
        $oldBackedUp = false;

        try {
            if ($oldPath !== null && $storage->exists($oldPath)) {
                $this->moveOrFail($storage, $oldPath, $backupPath);
                $oldBackedUp = true;
            }
            $this->moveOrFail($storage, $tempPath, $finalPath);

            try {
                $updated = $this->quota->transaction($owner, function (User $lockedOwner) use ($existing, $name, $extension, $mimeType, $size, $checksum, $finalPath): File {
                    $current = File::query()
                        ->whereKey($existing->getKey())
                        ->where('owner_id', $lockedOwner->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                    $delta = $size - (int) $current->size_bytes;
                    $this->quota->assertCanStoreLocked($lockedOwner, $delta);
                    $current->update([
                        'original_name' => $name,
                        'extension' => $extension,
                        'mime_type' => $mimeType,
                        'size_bytes' => $size,
                        'checksum' => $checksum,
                        'path' => $finalPath,
                    ]);
                    $this->quota->applyDeltaLocked($lockedOwner, $delta);
                    $this->activities->record($lockedOwner, ActivityAction::FileUploaded, $current, [
                        'sizeBytes' => $size,
                        'mimeType' => $mimeType,
                        'folderId' => $current->folder?->uuid,
                        'folderName' => $current->folder?->name,
                    ]);

                    return $current->fresh(['folder']);
                });
            } catch (Throwable $exception) {
                $this->cleanup($storage, [$finalPath]);
                if ($oldBackedUp) {
                    $this->moveOrFail($storage, $backupPath, $oldPath);
                }
                throw $exception;
            }

            $this->cleanup($storage, [$backupPath]);

            $this->storageNotifications->evaluate($owner);

            return $updated;
        } catch (Throwable $exception) {
            $this->cleanup($storage, [$tempPath, $finalPath]);
            if ($oldBackedUp && ! $storage->exists($oldPath)) {
                try {
                    $storage->move($backupPath, $oldPath);
                } catch (Throwable $cleanupException) {
                    Log::error('Unable to restore replaced file after upload failure.', ['path' => $oldPath, 'exception' => $cleanupException]);
                }
            }
            $this->cleanup($storage, [$backupPath]);
            throw $exception;
        }
    }

    private function stage(FilesystemAdapter $storage, UploadedFile $upload, string $tempPath): void
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

    private function moveOrFail(FilesystemAdapter $storage, string $from, string $to): void
    {
        try {
            if (! $storage->move($from, $to)) {
                throw new RuntimeException('Unable to move the uploaded file.');
            }
        } catch (Throwable $exception) {
            throw new HttpException(507, 'Storage is unavailable.', $exception);
        }
    }

    private function resolveFolder(User $owner, ?string $folderUuid): ?Folder
    {
        if ($folderUuid === null) {
            return null;
        }

        $folder = Folder::query()->ownedBy($owner)->where('uuid', $folderUuid)->notTrashed()->first();
        if ($folder === null) {
            throw ValidationException::withMessages(['folderId' => 'The target folder is invalid.']);
        }

        return $folder;
    }

    private function validatedName(string $rawName): string
    {
        $name = trim($rawName);
        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\')) {
            throw ValidationException::withMessages(['file' => 'The uploaded filename is invalid.']);
        }
        if (mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['file' => 'The uploaded filename is too long.']);
        }

        return $name;
    }

    private function assertExtensionAllowed(?string $extension): void
    {
        if ($extension !== null && in_array($extension, $this->settings->getJson('storage.blocked_extensions'), true)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }
    }

    private function extensionFrom(string $name): ?string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return $extension === '' ? null : mb_strtolower($extension);
    }

    private function findActiveDuplicate(User $owner, ?int $folderId, string $name): ?File
    {
        return File::query()->ownedBy($owner)->notTrashed()->where('folder_id', $folderId)->whereRaw('LOWER(original_name) = ?', [mb_strtolower($name)])->first();
    }

    private function uniqueName(User $owner, ?int $folderId, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
        $suffix = $extension === '' ? '' : '.'.$extension;
        for ($number = 1; $number < 1_000_000; $number++) {
            $candidate = $base.' ('.$number.')'.$suffix;
            if ($this->findActiveDuplicate($owner, $folderId, $candidate) === null) {
                return $candidate;
            }
        }
        throw new RuntimeException('Unable to generate a unique filename.');
    }

    private function finalPath(User $owner, string $uuid): string
    {
        return 'users/'.$owner->getKey().'/files/'.$uuid;
    }

    private function cleanup(FilesystemAdapter $storage, array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if ($path !== null && $storage->exists($path)) {
                try {
                    $storage->delete($path);
                } catch (Throwable $exception) {
                    Log::error('Unable to clean up uploaded file.', ['path' => $path, 'exception' => $exception]);
                }
            }
        }
    }

    private function translateDuplicate(Throwable $exception, User $owner, ?int $folderId, string $name): Throwable
    {
        if (! $exception instanceof QueryException || ! str_contains(mb_strtolower($exception->getMessage()), 'unique')) {
            return $exception;
        }

        $existing = $this->findActiveDuplicate($owner, $folderId, $name);

        return $existing === null ? $exception : new FileNameConflictException($existing);
    }
}
