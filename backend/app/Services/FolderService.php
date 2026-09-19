<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FolderService
{
    public function __construct(private readonly ActivityRecorder $activities) {}

    public function create(User $owner, string $name, ?string $parentUuid): Folder
    {
        $parent = $this->resolveParent($owner, $parentUuid);
        $this->assertNameAvailable($owner, $name, $parent?->getKey());

        return DB::transaction(function () use ($owner, $parent, $name): Folder {
            $folder = Folder::create(['owner_id' => $owner->getKey(), 'parent_id' => $parent?->getKey(), 'name' => $name]);
            $this->activities->record($owner, ActivityAction::FolderCreated, $folder, [
                'parentId' => $parent?->uuid,
                'parentName' => $parent?->name,
            ]);

            return $folder;
        });
    }

    public function rename(User $owner, Folder $folder, string $name): Folder
    {
        $this->assertNameAvailable($owner, $name, $folder->parent_id, $folder->getKey());
        $from = $folder->name;

        return DB::transaction(function () use ($owner, $folder, $name, $from): Folder {
            $folder->update(['name' => $name]);
            $this->activities->record($owner, ActivityAction::FolderRenamed, $folder, ['from' => $from, 'to' => $name]);

            return $folder->fresh(['parent']);
        });
    }

    public function move(User $owner, Folder $folder, ?string $parentUuid): Folder
    {
        $parent = $this->resolveParent($owner, $parentUuid);

        if ($parent?->getKey() === $folder->getKey()) {
            throw ValidationException::withMessages(['parentId' => 'A folder cannot be moved into itself.']);
        }

        if ($this->isDescendant($folder, $parent)) {
            throw ValidationException::withMessages(['parentId' => 'A folder cannot be moved into its descendant.']);
        }

        if ($parent?->getKey() === $folder->parent_id) {
            return $folder->fresh(['parent']);
        }

        $this->assertNameAvailable($owner, $folder->name, $parent?->getKey(), $folder->getKey());

        $fromParent = $folder->parent;

        return DB::transaction(function () use ($owner, $folder, $parent, $fromParent): Folder {
            $folder->update(['parent_id' => $parent?->getKey()]);
            $this->activities->record($owner, ActivityAction::FolderMoved, $folder, [
                'fromFolderId' => $fromParent?->uuid,
                'fromFolderName' => $fromParent?->name,
                'toFolderId' => $parent?->uuid,
                'toFolderName' => $parent?->name,
            ]);

            return $folder->fresh(['parent']);
        });
    }

    public function star(User $owner, Folder $folder): Folder
    {
        if (! $folder->is_starred) {
            DB::transaction(function () use ($owner, $folder): void {
                $folder->update(['is_starred' => true]);
                $this->activities->record($owner, ActivityAction::FolderStarred, $folder);
            });
        }

        return $folder->fresh(['parent']);
    }

    public function unstar(User $owner, Folder $folder): Folder
    {
        if ($folder->is_starred) {
            DB::transaction(function () use ($owner, $folder): void {
                $folder->update(['is_starred' => false]);
                $this->activities->record($owner, ActivityAction::FolderUnstarred, $folder);
            });
        }

        return $folder->fresh(['parent']);
    }

    public function breadcrumb(Folder $folder): array
    {
        $items = [];
        $current = $folder;
        $visited = [];

        while ($current !== null) {
            if (isset($visited[$current->getKey()])) {
                break;
            }
            $visited[$current->getKey()] = true;
            $items[] = ['id' => $current->uuid, 'name' => $current->name];
            $current = $current->parent;
        }

        return array_reverse($items);
    }

    private function resolveParent(User $owner, ?string $parentUuid): ?Folder
    {
        if ($parentUuid === null) {
            return null;
        }

        $parent = Folder::query()
            ->ownedBy($owner)
            ->where('uuid', $parentUuid)
            ->notTrashed()
            ->first();

        if ($parent === null) {
            throw ValidationException::withMessages(['parentId' => 'The parent folder is invalid.']);
        }

        return $parent;
    }

    private function assertNameAvailable(User $owner, string $name, ?int $parentId, ?int $ignoreId = null): void
    {
        $query = Folder::query()
            ->ownedBy($owner)
            ->notTrashed()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'A folder with this name already exists here.']);
        }
    }

    private function isDescendant(Folder $folder, ?Folder $parent): bool
    {
        $current = $parent;
        $visited = [];

        while ($current !== null) {
            if ($current->getKey() === $folder->getKey()) {
                return true;
            }
            if (isset($visited[$current->getKey()])) {
                return true;
            }
            $visited[$current->getKey()] = true;
            $current = $current->parent;
        }

        return false;
    }
}
