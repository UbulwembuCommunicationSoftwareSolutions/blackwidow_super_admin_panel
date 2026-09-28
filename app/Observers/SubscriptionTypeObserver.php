<?php

namespace App\Observers;

use App\Jobs\PropagateReleaseToDeploymentScriptsJob;
use App\Models\SubscriptionType;

class SubscriptionTypeObserver
{
    public function saving(SubscriptionType $type): void
    {
        if (! $type->isDirty('current_release_id')) {
            return;
        }

        if ($type->current_release_id === null) {
            return;
        }

        $tag = $type->currentRelease()?->tag
            ?? $type->releases()->whereKey($type->current_release_id)->value('tag');

        if (is_string($tag) && $tag !== '') {
            $type->master_version = $tag;
        }
    }

    /**
     * Promoting a release (console, backend API, or auto-promote on sync) re-renders every
     * non-pinned site's Forge deployment script so the next deploy checks out the new tag.
     */
    public function updated(SubscriptionType $type): void
    {
        if (! $type->wasChanged('current_release_id') || $type->current_release_id === null) {
            return;
        }

        PropagateReleaseToDeploymentScriptsJob::dispatch((int) $type->id, (int) $type->current_release_id);
    }
}
