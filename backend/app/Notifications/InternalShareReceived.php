<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternalShareReceived extends Notification
{
    use Queueable;

    public function __construct(
        private readonly File|Folder $item,
        private readonly string $permission,
        private readonly int $actorId,
        private readonly string $actorName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'share.received';
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload([
            'permission' => $this->permission,
            'sharedAt' => now()->toISOString(),
        ]);
    }

    private function payload(array $extra): array
    {
        $type = $this->item instanceof File ? 'file' : 'folder';
        $name = $this->item instanceof File ? $this->item->original_name : $this->item->name;

        return array_merge([
            'type' => 'share.received',
            'itemType' => $type,
            'itemId' => $this->item->uuid,
            'itemName' => $name,
            'actor' => ['id' => $this->actorId, 'name' => $this->actorName],
            'target' => ['kind' => 'shared-item', 'itemType' => $type, 'itemId' => $this->item->uuid],
        ], $extra);
    }
}
