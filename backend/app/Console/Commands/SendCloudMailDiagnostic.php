<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCloudMailDiagnostic extends Command
{
    protected $signature = 'cloud:mail-test {recipient : One controlled test email address} {--force : Confirm a one-recipient send in production}';

    protected $description = 'Send one provider-agnostic Cloud mail delivery diagnostic';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('The recipient must be a valid email address.');

            return self::FAILURE;
        }

        if (app()->environment('production')) {
            if (! $this->option('force')) {
                $this->error('In production, pass --force after confirming this is a controlled test recipient.');

                return self::FAILURE;
            }

            if (! $this->productionSmtpIsConfigured()) {
                $this->error('Production mail must use authenticated SMTP with TLS and a configured sender. No email was sent.');

                return self::FAILURE;
            }
        }

        try {
            Mail::mailer(config('mail.default'))->raw(
                'This is a one-time Cloud by NasLabs mail delivery diagnostic. No action is required.',
                function ($message) use ($recipient): void {
                    $message->to($recipient)->subject('Cloud by NasLabs delivery check');
                },
            );
        } catch (Throwable $exception) {
            $recipientDomain = substr(strrchr($recipient, '@') ?: '', 1);
            Log::warning('Cloud mail diagnostic failed.', [
                'exception_class' => $exception::class,
                'recipient_domain' => $recipientDomain,
            ]);
            $this->error('The configured mail transport rejected the diagnostic ('.$exception::class.'). Check protected provider logs.');

            return self::FAILURE;
        }

        $this->info('The configured transport accepted the diagnostic. Confirm inbox/spam delivery separately.');

        return self::SUCCESS;
    }

    private function productionSmtpIsConfigured(): bool
    {
        $smtp = config('mail.mailers.smtp');
        $fromAddress = config('mail.from.address');
        $scheme = $smtp['scheme'] ?: ((int) $smtp['port'] === 465 ? 'smtps' : 'smtp');
        $securePort = ($scheme === 'smtps' && (int) $smtp['port'] === 465)
            || ($scheme === 'smtp' && (int) $smtp['port'] === 587);

        return config('mail.default') === 'smtp'
            && filled($smtp['host'])
            && filled($smtp['username'])
            && filled($smtp['password'])
            && (float) $smtp['timeout'] > 0
            && filled($fromAddress)
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false
            && $securePort;
    }
}
