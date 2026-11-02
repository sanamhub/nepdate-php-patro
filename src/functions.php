<?php

declare(strict_types=1);

namespace NepDate\Patro;

use NepDate\NepaliDate;
use NepDate\NepaliDateRange;
use NepDate\Patro\Internal\Lookup;
use NepDate\Patro\Internal\PatroTable;

/**
 * The patro query surface (PATRO.md §2, API.md NT07): a free function, not a static method,
 * matching every other port's module-level `info(d)`.
 */
function info(NepaliDate $date): CalendarInfo
{
    $serial = $date->serial();

    $tithi = null;
    if ($serial >= PatroTable::TITHI_FIRST_SERIAL && $serial <= PatroTable::TITHI_LAST_SERIAL) {
        $index = PatroTable::TITHI_INDEX[$serial - PatroTable::TITHI_FIRST_SERIAL];
        $tithi = new Tithi(PatroTable::NAMES[$index][0], PatroTable::NAMES[$index][1]);
    }

    $isHoliday = Lookup::contains(PatroTable::HOLIDAY_SERIALS, $serial);

    $events = [];
    $dayIndex = Lookup::indexOf(PatroTable::EVENT_DAY_SERIALS, $serial);
    if ($dayIndex >= 0) {
        $start = PatroTable::EVENT_START[$dayIndex];
        $end = PatroTable::EVENT_START[$dayIndex + 1];
        for ($i = $start; $i < $end; $i++) {
            $nameIndex = PatroTable::EVENTS_FLAT[$i];
            $events[] = new NepEvent(PatroTable::NAMES[$nameIndex][0], PatroTable::NAMES[$nameIndex][1]);
        }
    }

    return new CalendarInfo($tithi, $isHoliday, $events);
}

/** The smallest BS range containing every day with any tithi, holiday or event data (PATRO.md §2). */
function coverage(): NepaliDateRange
{
    return NepaliDateRange::of(
        dateFromSerial(PatroTable::FIRST_SERIAL),
        dateFromSerial(PatroTable::LAST_SERIAL),
    );
}

/** @return list<NepaliDate> every gazetted public holiday, ascending (PATRO.md §2) */
function publicHolidays(): array
{
    return array_map(dateFromSerial(...), PatroTable::HOLIDAY_SERIALS);
}

/** @internal */
function dateFromSerial(int $serial): NepaliDate
{
    [$y, $m, $d] = \NepDate\Internal\Calendar::fromSerial($serial);

    return NepaliDate::fromBs($y, $m, $d);
}
