<?php

namespace App\Support\UserSync;

enum PushOperation: string
{
    case Upsert = 'upsert';

    case Archive = 'archive';

    case Restore = 'restore';
}
