<?php

declare(strict_types=1);

namespace NepDate\Patro;

use NepDate\NepaliDate;
use NepDate\NepaliDateRange;
use NepDate\Patro\Internal\Lookup;
use NepDate\Patro\Internal\PatroTable;
use NepDate\Patro\Internal\Serials;

/**
 * Everything the patro knows about one BS date (PATRO.md §2, API.md NT07).
 *
 * @param NepaliDate $date the day to look up
 */
function info(NepaliDate $date): CalendarInfo
{
    $serial = $date->serial();

    $tithi = null;
    if ($serial >= PatroTable::TITHI_FIRST_SERIAL && $serial <= PatroTable::TITHI_LAST_SERIAL) {
        $tithi = new Tithi(...PatroTable::NAMES[PatroTable::TITHI_INDEX[$serial - PatroTable::TITHI_FIRST_SERIAL]]);
    }

    $events = [];
    $dayIndex = Lookup::indexOf(PatroTable::EVENT_DAY_SERIALS, $serial);
    if ($dayIndex >= 0) {
        $start = PatroTable::EVENT_START[$dayIndex];
        $end = PatroTable::EVENT_START[$dayIndex + 1];
        for ($i = $start; $i < $end; $i++) {
            $events[] = new NepEvent(...PatroTable::NAMES[PatroTable::EVENTS_FLAT[$i]]);
        }
    }

    return new CalendarInfo($tithi, Lookup::contains(PatroTable::HOLIDAY_SERIALS, $serial), $events);
}

/**
 * The smallest BS range containing every day with any tithi, holiday or event data
 * (PATRO.md §2).
 */
function coverage(): NepaliDateRange
{
    return NepaliDateRange::of(
        Serials::toDate(PatroTable::FIRST_SERIAL),
        Serials::toDate(PatroTable::LAST_SERIAL),
    );
}

/**
 * Every gazetted public holiday, ascending (PATRO.md §2).
 *
 * @return list<NepaliDate>
 */
function publicHolidays(): array
{
    return array_map(Serials::toDate(...), PatroTable::HOLIDAY_SERIALS);
}
