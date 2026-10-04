<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendPushTest extends Command
{
    protected $signature = 'cloud:push-test {user : User ID or email address}';

    protected $description = 'Send a local-only Drive by NasLabs test push to a user\'s active browser subscriptions.';

    public function handle(PushNotificationService $push): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is only available in local or testing environments.');

            return self::FAILURE;
        }

        $value = (string) $this->argument('user');
        $user = User::query()
            ->when(ctype_digit($value), fn ($query) => $query->whereKey((int) $value))
            ->when(! ctype_digit($value), fn ($query) => $query->where('email', $value))
            ->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $push->send($user, 'account.security', 'Drive test notification', 'This is a local Web Push test.', '/settings/security');
        $this->info('Push test dispatched to the user\'s active subscriptions.');

        return self::SUCCESS;
    }
}
