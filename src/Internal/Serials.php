<?php

declare(strict_types=1);

namespace NepDate\Patro\Internal;

use NepDate\Internal\Calendar;
use NepDate\NepaliDate;

/**
 * The serial-to-date step the patro functions need, kept out of the package's public surface so
 * that `info()`, `coverage()` and `publicHolidays()` read as the free functions of `API.md` NT07.
 *
 * @internal
 */
final class Serials
{
    public static function toDate(int $serial): NepaliDate
    {
        [$year, $month, $day] = Calendar::fromSerial($serial);

        return NepaliDate::fromBs($year, $month, $day);
    }
}
