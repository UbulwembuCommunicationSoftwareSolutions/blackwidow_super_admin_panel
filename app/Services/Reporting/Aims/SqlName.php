<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use InvalidArgumentException;

final class SqlName
{
    public static function assert(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('SQL name must be a plain identifier.');
        }

        return $name;
    }

    public static function qualify(string $database, string $object): string
    {
        self::assert($database);
        self::assert($object);

        return '`'.$database.'`.`'.$object.'`';
    }
}
