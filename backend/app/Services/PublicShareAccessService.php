<?php

namespace App\Services;

use App\Exceptions\PublicShareUnavailableException;
use App\Models\File;
use App\Models\Folder;

class PublicShareAccessService
{
    public function __construct(private readonly PublicShareService $public) {}

    public function root(string $token): array
    {
        return $this->public->resolve($token);
    }

    public function file(string $token, File $file): array
    {
        $context = $this->root($token);
        if ($context['type'] === 'file') {
            if ($context['item']->getKey() !== $file->getKey()) {
                throw new PublicShareUnavailableException;
            }

            return $context;
        }
        $root = $context['item'];
        if ($file->owner_id !== $root->owner_id || $file->trashed_at !== null || ! $this->withinFolder($file->folder, $root)) {
            throw new PublicShareUnavailableException;
        }

        return $context;
    }

    public function folder(string $token, ?string $folderUuid = null): array
    {
        $context = $this->root($token);
        if ($context['type'] !== 'folder') {
            throw new PublicShareUnavailableException;
        }
        $root = $context['item'];
        if ($folderUuid === null) {
            return $context + ['currentFolder' => $root];
        }
        $folder = Folder::query()->where('uuid', $folderUuid)->where('owner_id', $root->owner_id)->notTrashed()->first();
        if ($folder === null || ! $this->withinFolder($folder, $root)) {
            throw new PublicShareUnavailableException;
        }

        return $context + ['currentFolder' => $folder];
    }

    private function withinFolder(?Folder $folder, Folder $root): bool
    {
        $current = $folder;
        while ($current !== null) {
            if ($current->getKey() === $root->getKey()) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }
}
