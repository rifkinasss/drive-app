<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FileService
{
    public function __construct(private readonly ActivityRecorder $activities) {}

    public function rename(User $owner, File $file, string $name, ?User $actor = null): File
    {
        $this->assertNameAvailable($owner, $name, $file->folder_id, $file->getKey());
        $from = $file->original_name;

        return DB::transaction(function () use ($owner, $file, $name, $from, $actor): File {
            $file->update(['original_name' => $name, 'extension' => $this->extensionFrom($name)]);
            $this->activities->recordFor($owner, $actor ?? $owner, ActivityAction::FileRenamed, $file, ['from' => $from, 'to' => $name]);

            return $file->fresh(['folder']);
        });
    }

    public function move(User $owner, File $file, ?string $folderUuid): File
    {
        $folder = $this->resolveFolder($owner, $folderUuid);

        if ($folder?->getKey() === $file->folder_id) {
            return $file->fresh(['folder']);
        }

        $fromFolder = $file->folder;
        $this->assertNameAvailable($owner, $file->original_name, $folder?->getKey(), $file->getKey());

        return DB::transaction(function () use ($owner, $file, $folder, $fromFolder): File {
            $file->update(['folder_id' => $folder?->getKey()]);
            $this->activities->record($owner, ActivityAction::FileMoved, $file, [
                'fromFolderId' => $fromFolder?->uuid,
                'fromFolderName' => $fromFolder?->name,
                'toFolderId' => $folder?->uuid,
                'toFolderName' => $folder?->name,
            ]);

            return $file->fresh(['folder']);
        });
    }

    public function star(User $owner, File $file): File
    {
        if (! $file->is_starred) {
            DB::transaction(function () use ($owner, $file): void {
                $file->update(['is_starred' => true]);
                $this->activities->record($owner, ActivityAction::FileStarred, $file);
            });
        }

        return $file->fresh(['folder']);
    }

    public function unstar(User $owner, File $file): File
    {
        if ($file->is_starred) {
            DB::transaction(function () use ($owner, $file): void {
                $file->update(['is_starred' => false]);
                $this->activities->record($owner, ActivityAction::FileUnstarred, $file);
            });
        }

        return $file->fresh(['folder']);
    }

    private function resolveFolder(User $owner, ?string $folderUuid): ?Folder
    {
        if ($folderUuid === null) {
            return null;
        }

        $folder = Folder::query()
            ->ownedBy($owner)
            ->where('uuid', $folderUuid)
            ->notTrashed()
            ->first();

        if ($folder === null) {
            throw ValidationException::withMessages(['folderId' => 'The target folder is invalid.']);
        }

        return $folder;
    }

    private function assertNameAvailable(User $owner, string $name, ?int $folderId, ?int $ignoreId = null): void
    {
        $query = File::query()
            ->ownedBy($owner)
            ->notTrashed()
            ->where('folder_id', $folderId)
            ->whereRaw('LOWER(original_name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'A file with this name already exists here.']);
        }
    }

    private function extensionFrom(string $name): ?string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return $extension === '' ? null : mb_strtolower($extension);
    }
}
