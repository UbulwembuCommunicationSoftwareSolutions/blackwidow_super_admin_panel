<?php

namespace App\Support\BrandingSync;

enum BrandingSyncOutcome: string
{
    case Created = 'created';

    case Updated = 'updated';

    /**
     * The incoming record was older than ours, so ours stands and is returned
     * to the caller as the authoritative copy.
     */
    case Stale = 'stale';

    case Cleared = 'cleared';

    /**
     * Incoming checksum matches the local copy — no write needed.
     */
    case Unchanged = 'unchanged';
}
