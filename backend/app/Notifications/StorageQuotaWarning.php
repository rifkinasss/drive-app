<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StorageQuotaWarning extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $threshold,
        private readonly int $usedBytes,
        private readonly int $quotaBytes,
        private readonly float $usagePercentage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'storage.quota_warning';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'storage.quota_warning',
            'threshold' => $this->threshold,
            'usedBytes' => $this->usedBytes,
            'quotaBytes' => $this->quotaBytes,
            'usagePercentage' => $this->usagePercentage,
            'target' => ['kind' => 'storage'],
        ];
    }
}
