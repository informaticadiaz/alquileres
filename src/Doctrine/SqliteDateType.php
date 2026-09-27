<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateType;

/**
 * Stores date columns as "Y-m-d 00:00:00" on SQLite.
 *
 * SQLite compares dates as text, and DQL parameters built from DateTime objects are
 * bound as "Y-m-d H:i:s". A plain "Y-m-d" value would sort before the same day at
 * midnight, so range queries against date columns silently miss matches. Reading still
 * accepts plain "Y-m-d" values written by raw SQL.
 */
final class SqliteDateType extends DateType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $value = parent::convertToDatabaseValue($value, $platform);

        return null === $value ? null : SqliteDateFormat::toStorage($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        return parent::convertToPHPValue(\is_string($value) ? SqliteDateFormat::fromStorage($value) : $value, $platform);
    }
}
