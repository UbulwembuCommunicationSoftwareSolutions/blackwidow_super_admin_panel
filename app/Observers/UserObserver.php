<?php

namespace App\Observers;

use App\Jobs\PushStaffUserToLmsJob;
use App\Models\User;
use App\Services\Lms\LmsStaffPusher;

/**
 * Staff users are the shared LMS's admin users; changes follow them there.
 */
class UserObserver
{
    public function __construct(private readonly LmsStaffPusher $lms) {}

    public function created(User $user): void
    {
        if ($this->lms->enabled()) {
            PushStaffUserToLmsJob::dispatch($user->id);
        }
    }

    public function updated(User $user): void
    {
        if ($this->lms->enabled() && $user->wasChanged(['name', 'email', 'password'])) {
            PushStaffUserToLmsJob::dispatch($user->id);
        }
    }

    public function deleted(User $user): void
    {
        if ($this->lms->enabled()) {
            PushStaffUserToLmsJob::dispatch($user->id, archive: true);
        }
    }
}
