<?php

declare(strict_types=1);

namespace App\Doctrine;

/**
 * Converts between the platform date format ("Y-m-d") and the SQLite storage format
 * ("Y-m-d 00:00:00") used by the SQLite date types.
 */
final class SqliteDateFormat
{
    private const MIDNIGHT = ' 00:00:00';

    public static function toStorage(string $date): string
    {
        return $date.self::MIDNIGHT;
    }

    public static function fromStorage(string $stored): string
    {
        return str_ends_with($stored, self::MIDNIGHT) ? substr($stored, 0, -\strlen(self::MIDNIGHT)) : $stored;
    }
}
