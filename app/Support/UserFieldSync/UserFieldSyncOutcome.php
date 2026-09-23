<?php

namespace App\Support\UserFieldSync;

enum UserFieldSyncOutcome: string
{
    case Created = 'created';

    case Updated = 'updated';

    /**
     * The incoming record was older than ours, so ours stands and is returned
     * to the caller as the authoritative copy.
     */
    case Stale = 'stale';

    case Unchanged = 'unchanged';

    case Archived = 'archived';

    case Restored = 'restored';

    case Cleared = 'cleared';

    /**
     * The referenced user or field does not exist on the hub yet.
     * Caller should retry after reconcile.
     */
    case Unresolved = 'unresolved';
}
