<?php

namespace App\Support\UserSync;

enum SyncOutcome: string
{
    case Created = 'created';

    case Updated = 'updated';

    /**
     * The incoming record was older than ours, so ours stands and is returned
     * to the caller as the authoritative copy.
     */
    case Stale = 'stale';

    case Archived = 'archived';

    case Restored = 'restored';
}
