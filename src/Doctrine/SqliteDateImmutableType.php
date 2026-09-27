<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateImmutableType;

/**
 * Immutable counterpart of {@see SqliteDateType}.
 */
final class SqliteDateImmutableType extends DateImmutableType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $value = parent::convertToDatabaseValue($value, $platform);

        return null === $value ? null : SqliteDateFormat::toStorage($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        return parent::convertToPHPValue(\is_string($value) ? SqliteDateFormat::fromStorage($value) : $value, $platform);
    }
}
