<?php

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly UserInvitation $invitation,
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
        $url = rtrim((string) config('app.frontend_url'), '/').'/'.config('cloud.invitation_url_path').'/'.$this->token;

        return (new MailMessage)
            ->subject('You have been invited to Cloud by NasLabs')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("You've been invited to Cloud by NasLabs.")
            ->line('Account: '.$notifiable->email)
            ->action('Accept invitation', $url)
            ->line('This invitation expires '.$this->invitation->expires_at->diffForHumans().'.');
    }
}
