<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg\Builder;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

trait NodeValues
{
    private const string SCHEMA_ENUM_BASE = 'https://schema.org/';

    private function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string)$value);

        return $text !== '' ? $text : null;
    }

    private function enumUrl(mixed $value): ?string
    {
        $token = $this->text($value);
        if ($token === null) {
            return null;
        }

        if (str_starts_with($token, 'http://') || str_starts_with($token, 'https://')) {
            return $token;
        }

        return self::SCHEMA_ENUM_BASE . $token;
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        $raw = $this->text($value);
        if ($raw === null || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private function isoDateTime(mixed $value): ?string
    {
        return $this->dateTime($value)?->format(DateTimeInterface::ATOM);
    }

    private function isoDate(mixed $value): ?string
    {
        return $this->dateTime($value)?->format('Y-m-d');
    }

    private function reference(mixed $id): ?array
    {
        $value = $this->text($id);

        return $value !== null ? ['@id' => $value] : null;
    }
}
