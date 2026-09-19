<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternalSharePermissionChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly File|Folder $item,
        private readonly string $oldPermission,
        private readonly string $newPermission,
        private readonly int $actorId,
        private readonly string $actorName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'share.permission_changed';
    }

    public function toDatabase(object $notifiable): array
    {
        $type = $this->item instanceof File ? 'file' : 'folder';
        $name = $this->item instanceof File ? $this->item->original_name : $this->item->name;

        return [
            'type' => 'share.permission_changed',
            'itemType' => $type,
            'itemId' => $this->item->uuid,
            'itemName' => $name,
            'oldPermission' => $this->oldPermission,
            'newPermission' => $this->newPermission,
            'actor' => ['id' => $this->actorId, 'name' => $this->actorName],
            'target' => ['kind' => 'shared-item', 'itemType' => $type, 'itemId' => $this->item->uuid],
        ];
    }
}
