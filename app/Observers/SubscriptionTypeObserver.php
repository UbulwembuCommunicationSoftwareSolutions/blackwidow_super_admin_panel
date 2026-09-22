<?php

namespace App\Observers;

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
}
