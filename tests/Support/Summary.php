<?php

declare(strict_types=1);

namespace NepDate\Patro\Tests\Support;

use RuntimeException;

/**
 * Reads `shared/spec/vectors/patro-summary.json`, the counts `tools/make_vectors.py` derives from
 * `data/patro/` (PATRO.md §3). The tests compare the compiled tables against it rather than
 * pinning a count of their own, so a data update changes the data and the tables together.
 */
final class Summary
{
    public const PATH = __DIR__ . '/../../../../shared/spec/vectors/patro-summary.json';

    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    public static function int(string $key): int
    {
        $value = self::values()[$key] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException("patro-summary.json has no integer {$key}");
        }

        return $value;
    }

    public static function string(string $key): string
    {
        $value = self::values()[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException("patro-summary.json has no string {$key}");
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function values(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        $raw = file_get_contents(self::PATH);
        if ($raw === false) {
            throw new RuntimeException('cannot read ' . self::PATH);
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('patro-summary.json does not hold an object');
        }

        /** @var array<string, mixed> $decoded */
        return self::$values = $decoded;
    }
}
