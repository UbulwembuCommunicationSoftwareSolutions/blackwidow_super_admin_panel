<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Lms\LmsStaffPusher;
use Illuminate\Console\Command;

/**
 * Pushes every staff user to the shared LMS as an admin user, e.g. to seed
 * a new LMS or repair one. Safe to rerun.
 */
class LmsPushStaffCommand extends Command
{
    protected $signature = 'lms:push-staff {--dry-run : List the staff users without pushing}';

    protected $description = 'Push all Super Admin staff users to the shared LMS as admin users';

    public function handle(LmsStaffPusher $pusher): int
    {
        if (! $pusher->enabled()) {
            $this->error('The LMS hub URL, sync token or user sync is not configured.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach (User::query()->orderBy('id')->get() as $user) {
            if ($this->option('dry-run')) {
                $this->line("Would push staff user {$user->id} ({$user->email})");

                continue;
            }

            try {
                $result = $pusher->upsert($user);
                $this->line("Pushed staff user {$user->id} ({$user->email}): ".($result['outcome'] ?? 'ok').', LMS user '.($result['user']['id'] ?? '?'));
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Failed staff user {$user->id} ({$user->email}): {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
