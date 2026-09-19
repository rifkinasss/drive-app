<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StorageQuotaService;
use Illuminate\Console\Command;

class ReconcileStorageUsage extends Command
{
    protected $signature = 'cloud:storage-reconcile {--user= : User id or email} {--dry-run : Report differences without updating users}';

    protected $description = 'Reconcile user used storage from file metadata';

    public function handle(StorageQuotaService $quota): int
    {
        $users = User::query();
        if ($identifier = $this->option('user')) {
            $users->where(function ($query) use ($identifier): void {
                if (ctype_digit((string) $identifier)) {
                    $query->whereKey((int) $identifier);
                } else {
                    $query->where('email', mb_strtolower($identifier));
                }
            });
        }

        $count = 0;
        $mismatches = 0;
        foreach ($users->cursor() as $user) {
            $result = $quota->recalculate($user, (bool) $this->option('dry-run'));
            $count++;
            if ($result['difference'] !== 0) {
                $mismatches++;
                $this->line(sprintf(
                    '%s: stored=%d calculated=%d difference=%+d',
                    $user->email,
                    $result['stored'],
                    $result['calculated'],
                    $result['difference'],
                ));
            }
        }

        $this->info(sprintf('%d user(s) checked; %d mismatch(es)%s.', $count, $mismatches, $this->option('dry-run') ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
