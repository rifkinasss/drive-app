<?php

namespace App\Notifications;

use App\Models\EmailVerificationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EmailVerificationToken $verificationToken,
        private readonly string $token,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/'.config('cloud.verification_url_path').'/'.$this->token;

        return (new MailMessage)
            ->subject('Verify your Drive by NasLabs email')
            ->greeting('Drive by NasLabs')
            ->line('Please verify your email address to finish setting up your account.')
            ->action('Verify email', $url)
            ->line('This verification link expires '.$this->verificationToken->expires_at->diffForHumans().'.');
    }
}
