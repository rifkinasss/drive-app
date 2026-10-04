<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushNotificationService
{
    public function send(User $user, string $type, string $title, string $body, string $url): void
    {
        if (! $this->enabledFor($user, $type)) {
            return;
        }
        $publicKey = (string) config('cloud_settings.push.vapid_public_key');
        $privateKey = (string) config('cloud_settings.push.vapid_private_key');
        $subject = (string) config('cloud_settings.push.vapid_subject');
        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            return;
        }
        $safeUrl = str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/home';

        try {
            $webPush = $this->makeWebPush($subject, $publicKey, $privateKey);
            $subscriptions = PushSubscription::query()->where('user_id', $user->getKey())->get();
            foreach ($subscriptions as $stored) {
                $webPush->queueNotification($this->makeSubscription($stored), json_encode(['title' => $title, 'body' => $body, 'url' => $safeUrl, 'type' => $type], JSON_THROW_ON_ERROR));
            }
            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $this->removeExpired((string) $report->getEndpoint());
                } elseif (! $report->isSuccess()) {
                    Log::warning('Push delivery failed.', ['endpoint_hash' => $this->endpointHash((string) $report->getEndpoint()), 'reason' => $report->getReason()]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Push delivery could not be completed.', ['user_id' => $user->getKey(), 'exception' => $exception::class]);
        }
    }

    private function enabledFor(User $user, string $type): bool
    {
        $preferences = NotificationPreference::query()->where('user_id', $user->getKey())->first();
        $key = str_starts_with($type, 'share.') ? 'shares' : ($type === 'quota.warning' ? 'quota' : 'account_security');

        return $preferences?->{$key} ?? true;
    }

    private function removeExpired(string $endpoint): void
    {
        PushSubscription::query()->where('endpoint', $endpoint)->delete();
    }

    private function endpointHash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    protected function makeWebPush(string $subject, string $publicKey, string $privateKey): WebPush
    {
        return new WebPush(['VAPID' => ['subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey]], ['TTL' => 300]);
    }

    protected function makeSubscription(PushSubscription $stored): Subscription
    {
        return Subscription::create(['endpoint' => $stored->endpoint, 'keys' => ['p256dh' => $stored->p256dh, 'auth' => $stored->auth]]);
    }
}
