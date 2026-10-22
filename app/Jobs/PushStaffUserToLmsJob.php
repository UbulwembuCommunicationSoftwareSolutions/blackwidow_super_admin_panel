<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Lms\LmsStaffPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries a staff user change to the shared LMS, where staff are the admin
 * users. Queued so saving a staff user never waits on the LMS.
 */
class PushStaffUserToLmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $staffUserId,
        public bool $archive = false,
    ) {}

    public function handle(LmsStaffPusher $pusher): void
    {
        $user = User::query()->find($this->staffUserId);

        if ($this->archive || $user === null) {
            $pusher->archive($this->staffUserId);

            return;
        }

        $pusher->upsert($user);
    }
}
