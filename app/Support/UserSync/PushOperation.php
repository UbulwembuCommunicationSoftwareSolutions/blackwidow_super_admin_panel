<?php

namespace App\Support\UserSync;

enum PushOperation: string
{
    case Upsert = 'upsert';

    case Archive = 'archive';

    case Restore = 'restore';

    /** Push the user's granular permissions to every subscription they hold grants on. */
    case Permissions = 'permissions';
}
