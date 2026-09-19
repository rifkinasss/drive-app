<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Exceptions\TrashConflictException;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\PublicShareLink;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class FileTrashService
{
    public function __construct(private readonly StorageQuotaService $quota, private readonly ActivityRecorder $activities, private readonly StorageNotificationService $storageNotifications) {}

    public function deleteExpiredFile(User $owner, int $fileId): array
    {
        $file = File::query()->ownedBy($owner)->whereKey($fileId)->whereNotNull('trashed_at')->firstOrFail();

        return $this->permanentFile($owner, $file, false);
    }

    public function deleteExpiredFolder(User $owner, int $folderId): array
    {
        $folder = Folder::query()->ownedBy($owner)->whereKey($folderId)->whereNotNull('trashed_at')->firstOrFail();
        [$folderIds, $fileIds] = $this->expiredFolderSelection($owner, $folder);

        return $this->permanentSelection($owner, $folderIds, $fileIds, $folder, false, false);
    }

    public function estimateExpiredFolder(User $owner, Folder $folder): array
    {
        [$folderIds, $fileIds] = $this->expiredFolderSelection($owner, $folder);
        $files = File::query()->ownedBy($owner)->whereIn('id', $fileIds)->whereNotNull('trashed_at');

        return ['files' => $files->count(), 'folders' => count($folderIds), 'bytes' => (int) $files->sum('size_bytes')];
    }

    private function expiredFolderSelection(User $owner, Folder $folder): array
    {
        $batchId = $folder->trash_batch_id;
        $folderIds = $this->collectFolderIds($owner, $folder->getKey());

        if ($batchId !== null) {
            $folderIds = Folder::query()->ownedBy($owner)->whereIn('id', $folderIds)->where('trash_batch_id', $batchId)->pluck('id')->all();
        }

        $fileIds = File::query()->ownedBy($owner)->whereIn('folder_id', $folderIds)->whereNotNull('trashed_at')
            ->when($batchId !== null, fn ($query) => $query->where('trash_batch_id', $batchId))->pluck('id')->all();

        return [$folderIds, $fileIds];
    }

    public function trashFile(User $owner, File $file): File
    {
        if ($file->trashed_at !== null) {
            return $file->fresh(['folder']);
        }

        DB::transaction(function () use ($owner, $file): void {
            $file->update(['trashed_at' => now(), 'trash_batch_id' => (string) str()->uuid()]);
            $this->activities->record($owner, ActivityAction::FileTrashed, $file);
        });

        return $file->fresh(['folder']);
    }

    public function restoreFile(User $owner, File $file, ?string $strategy = null): File
    {
        if ($file->trashed_at === null) {
            return $file->fresh(['folder']);
        }

        $folder = $this->activeFolder($owner, $file->folder_id);
        $name = $file->original_name;
        $existing = $this->activeFile($owner, $folder?->getKey(), $name, $file->getKey());
        if ($existing !== null) {
            if ($strategy !== 'keep_both') {
                throw new TrashConflictException('file', $existing->uuid, $name);
            }
            $name = $this->uniqueFileName($owner, $folder?->getKey(), $name);
        }

        return DB::transaction(function () use ($owner, $file, $folder, $name): File {
            $file->update([
                'folder_id' => $folder?->getKey(),
                'original_name' => $name,
                'extension' => $this->extensionFrom($name),
                'trashed_at' => null,
                'trash_batch_id' => null,
            ]);
            $this->activities->record($owner, ActivityAction::FileRestored, $file, [
                'folderId' => $folder?->uuid,
                'folderName' => $folder?->name,
            ]);

            return $file->fresh(['folder']);
        });
    }

    public function trashFolder(User $owner, Folder $folder): Folder
    {
        if ($folder->trashed_at !== null) {
            return $folder->fresh(['parent']);
        }

        $ids = $this->descendantFolderIds($owner, $folder);
        $batchId = (string) str()->uuid();
        $trashedAt = now();

        DB::transaction(function () use ($owner, $folder, $ids, $batchId, $trashedAt): void {
            Folder::query()->whereKey($folder->getKey())->where('owner_id', $owner->getKey())->update([
                'trashed_at' => $trashedAt,
                'trash_batch_id' => $batchId,
            ]);
            Folder::query()->whereIn('id', $ids)->where('owner_id', $owner->getKey())->whereNull('trashed_at')->update([
                'trashed_at' => $trashedAt,
                'trash_batch_id' => $batchId,
            ]);
            File::query()->whereIn('folder_id', $ids)->where('owner_id', $owner->getKey())->whereNull('trashed_at')->update([
                'trashed_at' => $trashedAt,
                'trash_batch_id' => $batchId,
            ]);
            $this->activities->record($owner, ActivityAction::FolderTrashed, $folder);
        });

        return $folder->fresh(['parent']);
    }

    public function restoreFolder(User $owner, Folder $folder, ?string $strategy = null): Folder
    {
        if ($folder->trashed_at === null) {
            return $folder->fresh(['parent']);
        }

        $parent = $this->activeFolder($owner, $folder->parent_id);
        $name = $folder->name;
        $existing = $this->activeFolderByName($owner, $parent?->getKey(), $name, $folder->getKey());
        if ($existing !== null) {
            if ($strategy !== 'keep_both') {
                throw new TrashConflictException('folder', $existing->uuid, $name);
            }
            $name = $this->uniqueFolderName($owner, $parent?->getKey(), $name);
        }

        $batchId = $folder->trash_batch_id;

        return DB::transaction(function () use ($owner, $folder, $parent, $name, $batchId): Folder {
            $folder->update([
                'parent_id' => $parent?->getKey(),
                'name' => $name,
                'trashed_at' => null,
                'trash_batch_id' => null,
            ]);

            if ($batchId !== null) {
                Folder::query()->where('owner_id', $owner->getKey())->where('trash_batch_id', $batchId)->whereKeyNot($folder->getKey())->update([
                    'trashed_at' => null,
                    'trash_batch_id' => null,
                ]);
                File::query()->where('owner_id', $owner->getKey())->where('trash_batch_id', $batchId)->update([
                    'trashed_at' => null,
                    'trash_batch_id' => null,
                ]);
            }
            $this->activities->record($owner, ActivityAction::FolderRestored, $folder, [
                'parentId' => $parent?->uuid,
                'parentName' => $parent?->name,
            ]);

            return $folder->fresh(['parent']);
        });
    }

    public function permanentFile(User $owner, File $file, bool $recordActivity = true): array
    {
        $this->assertTrashed($file->trashed_at !== null);
        $staged = $this->stagePhysical($file);
        try {
            $result = $this->quota->transaction($owner, function (User $lockedOwner) use ($file, $recordActivity): array {
                $current = File::query()->whereKey($file->getKey())->where('owner_id', $lockedOwner->getKey())->whereNotNull('trashed_at')->lockForUpdate()->firstOrFail();
                $size = (int) $current->size_bytes;
                $name = $current->original_name;
                InternalShare::query()->where('shareable_type', 'file')->where('shareable_id', $current->getKey())->delete();
                PublicShareLink::query()->where('shareable_type', 'file')->where('shareable_id', $current->getKey())->delete();
                $current->delete();
                $this->quota->applyDeltaLocked($lockedOwner, -$size);
                if ($recordActivity) {
                    $this->activities->record($lockedOwner, ActivityAction::FileDeleted, $current, [], $name);
                }

                return ['deletedFiles' => 1, 'deletedFolders' => 0, 'freedBytes' => $size];
            });
            $this->removeStaged($staged);
            if ($recordActivity) {
                $this->storageNotifications->evaluate($owner);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->restoreStaged($staged);
            throw $exception;
        }
    }

    public function permanentFolder(User $owner, Folder $folder): array
    {
        $this->assertTrashed($folder->trashed_at !== null);

        return $this->permanentFolderIds($owner, [$folder->getKey()], $folder);
    }

    public function emptyTrash(User $owner): array
    {
        $folderRoots = Folder::query()->ownedBy($owner)->whereNotNull('trashed_at')->where(function ($query): void {
            $query->whereNull('parent_id')->orWhereDoesntHave('parent', fn ($parent) => $parent->whereNotNull('trashed_at'));
        })->pluck('id')->all();
        $folderIds = [];
        foreach ($folderRoots as $folderId) {
            $folderIds = array_merge($folderIds, $this->collectFolderIds($owner, (int) $folderId));
        }
        $folderIds = array_values(array_unique($folderIds));
        $fileIds = File::query()->ownedBy($owner)->whereNotNull('trashed_at')->where(function ($query) use ($folderIds): void {
            $query->whereNull('folder_id');
            if ($folderIds !== []) {
                $query->orWhereIn('folder_id', $folderIds);
            }
        })->pluck('id')->all();

        return $this->permanentSelection($owner, $folderIds, $fileIds, null, true);
    }

    public function permanentFolderIds(User $owner, array $rootIds, ?Folder $root = null): array
    {
        $folderIds = [];
        foreach ($rootIds as $rootId) {
            $folderIds = array_merge($folderIds, $this->collectFolderIds($owner, (int) $rootId));
        }
        $folderIds = array_values(array_unique($folderIds));
        $fileIds = File::query()->ownedBy($owner)->whereNotNull('trashed_at')->whereIn('folder_id', $folderIds)->pluck('id')->all();

        return $this->permanentSelection($owner, $folderIds, $fileIds, $root);
    }

    private function permanentSelection(User $owner, array $folderIds, array $fileIds, ?Folder $root = null, bool $emptyTrash = false, bool $recordActivity = true): array
    {
        $files = File::query()->ownedBy($owner)->whereIn('id', $fileIds)->whereNotNull('trashed_at')->get();
        $staged = [];
        try {
            foreach ($files as $file) {
                $staged[] = $this->stagePhysical($file);
            }
            $result = $this->quota->transaction($owner, function (User $lockedOwner) use ($folderIds, $fileIds, $root, $emptyTrash, $recordActivity): array {
                $files = File::query()->where('owner_id', $lockedOwner->getKey())->whereIn('id', $fileIds)->whereNotNull('trashed_at')->lockForUpdate()->get();
                $freedBytes = (int) $files->sum('size_bytes');
                $deletedFiles = $files->count();
                $folders = Folder::query()->where('owner_id', $lockedOwner->getKey())->whereIn('id', $folderIds)->whereNotNull('trashed_at')->lockForUpdate()->get();
                $lockedFolderIds = $folders->modelKeys();
                if (! $emptyTrash && $lockedFolderIds === [] && $files->isEmpty()) {
                    throw new RuntimeException('Trash selection changed before permanent deletion.');
                }
                $deletedFolders = count($lockedFolderIds);
                File::query()->whereIn('id', $files->modelKeys())->delete();
                InternalShare::query()->where(function ($query) use ($files, $lockedFolderIds): void {
                    $query->where(function ($nested) use ($files): void {
                        $nested->where('shareable_type', 'file')->whereIn('shareable_id', $files->modelKeys());
                    });
                    if ($lockedFolderIds !== []) {
                        $query->orWhere(function ($nested) use ($lockedFolderIds): void {
                            $nested->where('shareable_type', 'folder')->whereIn('shareable_id', $lockedFolderIds);
                        });
                    }
                })->delete();
                PublicShareLink::query()->where(function ($query) use ($files, $lockedFolderIds): void {
                    $query->where(function ($nested) use ($files): void {
                        $nested->where('shareable_type', 'file')->whereIn('shareable_id', $files->modelKeys());
                    });
                    if ($lockedFolderIds !== []) {
                        $query->orWhere(function ($nested) use ($lockedFolderIds): void {
                            $nested->where('shareable_type', 'folder')->whereIn('shareable_id', $lockedFolderIds);
                        });
                    }
                })->delete();
                if ($lockedFolderIds !== []) {
                    Folder::query()->where('owner_id', $lockedOwner->getKey())->whereIn('id', $lockedFolderIds)->delete();
                }
                $this->quota->applyDeltaLocked($lockedOwner, -$freedBytes);
                if ($recordActivity && $root !== null) {
                    $this->activities->record($lockedOwner, ActivityAction::FolderDeleted, $root, [], $root->name);
                } elseif ($recordActivity && $emptyTrash) {
                    $this->activities->record($lockedOwner, ActivityAction::TrashEmptied, null, [
                        'deletedFiles' => $deletedFiles,
                        'deletedFolders' => $deletedFolders,
                        'freedBytes' => $freedBytes,
                    ]);
                }

                return ['deletedFiles' => $deletedFiles, 'deletedFolders' => $deletedFolders, 'freedBytes' => $freedBytes];
            });
            foreach ($staged as $path) {
                $this->removeStaged($path);
            }
            if ($recordActivity) {
                $this->storageNotifications->evaluate($owner);
            }

            return $result;
        } catch (Throwable $exception) {
            foreach (array_reverse($staged) as $path) {
                $this->restoreStaged($path);
            }
            throw $exception;
        }
    }

    private function descendantFolderIds(User $owner, Folder $folder): array
    {
        return $this->collectFolderIds($owner, $folder->getKey(), false);
    }

    private function collectFolderIds(User $owner, int $rootId, bool $includeRoot = true): array
    {
        $ids = $includeRoot ? [$rootId] : [$rootId];
        $pending = [$rootId];
        while ($pending !== []) {
            $children = Folder::query()->ownedBy($owner)->whereIn('parent_id', $pending)->pluck('id')->all();
            $pending = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $pending);
        }

        return array_values(array_unique($ids));
    }

    private function activeFolder(User $owner, ?int $folderId): ?Folder
    {
        return $folderId === null ? null : Folder::query()->ownedBy($owner)->whereKey($folderId)->notTrashed()->first();
    }

    private function activeFile(User $owner, ?int $folderId, string $name, int $ignoreId): ?File
    {
        return File::query()->ownedBy($owner)->notTrashed()->where('folder_id', $folderId)->whereKeyNot($ignoreId)->whereRaw('LOWER(original_name) = ?', [mb_strtolower($name)])->first();
    }

    private function activeFolderByName(User $owner, ?int $parentId, string $name, int $ignoreId): ?Folder
    {
        return Folder::query()->ownedBy($owner)->notTrashed()->where('parent_id', $parentId)->whereKeyNot($ignoreId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    private function uniqueFileName(User $owner, ?int $folderId, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
        $suffix = $extension === '' ? '' : '.'.$extension;
        for ($number = 1; $number < 1_000_000; $number++) {
            $candidate = $base.' ('.$number.')'.$suffix;
            if ($this->activeFile($owner, $folderId, $candidate, 0) === null) {
                return $candidate;
            }
        }
        throw new RuntimeException('Unable to generate a unique filename.');
    }

    private function uniqueFolderName(User $owner, ?int $parentId, string $name): string
    {
        for ($number = 1; $number < 1_000_000; $number++) {
            $candidate = $name.' ('.$number.')';
            if ($this->activeFolderByName($owner, $parentId, $candidate, 0) === null) {
                return $candidate;
            }
        }
        throw new RuntimeException('Unable to generate a unique folder name.');
    }

    private function extensionFrom(string $name): ?string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return $extension === '' ? null : mb_strtolower($extension);
    }

    private function stagePhysical(File $file): ?array
    {
        if ($file->path === null) {
            return null;
        }
        $storage = $this->storage($file);
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

    private function removeStaged(?array $staged): void
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

    private function restoreStaged(?array $staged): void
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

    private function storage(File $file): FilesystemAdapter
    {
        try {
            return Storage::disk($file->disk ?: config('cloud.disk'));
        } catch (Throwable $exception) {
            throw new HttpException(507, 'Storage cleanup is unavailable.', $exception);
        }
    }

    private function assertTrashed(bool $condition): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['file' => 'Only trashed items can be permanently deleted.']);
        }
    }
}
