<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternalShareRevoked extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $itemType,
        private readonly string $itemId,
        private readonly string $itemName,
        private readonly int $actorId,
        private readonly string $actorName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'share.revoked';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'share.revoked',
            'itemType' => $this->itemType,
            'itemId' => $this->itemId,
            'itemName' => $this->itemName,
            'actor' => ['id' => $this->actorId, 'name' => $this->actorName],
            'target' => null,
        ];
    }
}
