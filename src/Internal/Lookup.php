<?php

declare(strict_types=1);

namespace NepDate\Patro\Internal;

/**
 * A plain binary search over an ascending `list<int>` of serials (ALGORITHM.md §4).
 *
 * @internal
 */
final class Lookup
{
    /**
     * The index of `$value` in `$sorted`, or -1 if absent. At most 11 comparisons for 2199 years.
     *
     * @param list<int> $sorted
     */
    public static function indexOf(array $sorted, int $value): int
    {
        $lo = 0;
        $hi = count($sorted) - 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $candidate = $sorted[$mid];
            if ($candidate === $value) {
                return $mid;
            }
            if ($candidate < $value) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return -1;
    }

    /**
     * Whether `$value` is present in the ascending `$sorted`.
     *
     * @param list<int> $sorted
     */
    public static function contains(array $sorted, int $value): bool
    {
        return self::indexOf($sorted, $value) >= 0;
    }
}
