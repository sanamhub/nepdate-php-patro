<?php

declare(strict_types=1);

namespace NepDate\Patro\Tests;

use NepDate\Lang;
use NepDate\NepaliDate;
use NepDate\NepaliDateRange;
use NepDate\Patro\CalendarInfo;
use NepDate\Patro\NepEvent;
use NepDate\Patro\Tests\Support\Summary;
use NepDate\Patro\Tithi;
use PHPUnit\Framework\TestCase;

use function NepDate\Patro\coverage;
use function NepDate\Patro\info;
use function NepDate\Patro\publicHolidays;

/**
 * The patro package against `PATRO.md`: the section 4 anchors, the query semantics of section 2
 * and the counts of `patro-summary.json`.
 */
final class PatroTest extends TestCase
{
    public function testTheAnchorDaysOfSection4(): void
    {
        $this->assertDay('2081-01-01', 'Panchami', true, ['New Year', 'Mesh Sankranti', 'Bisket Jatra']);
        $this->assertDay('2081-01-02', 'Shashthi', false, []);
        $this->assertDay('2081-02-15', 'Panchami', true, ['Republic Day']);
        $this->assertDay('2080-11-01', 'Chaturthi', false, [
            'Mangala Chauthi Brata',
            "People's War Day",
            'Tilakund Chauthi',
            'Kumbha Sankranti',
            'World Radio Day',
        ]);
        $this->assertDay('2081-04-15', 'Dashami', false, [
            'Kheer Khane Din',
            'World Day Against Human Trafficking',
            'International Friendship Day',
        ]);
    }

    public function testADayWithoutDataIsEmptyRatherThanFailing(): void
    {
        foreach (['2070-06-15', '2090-01-01', '1901-01-01', '2199-12-30'] as $text) {
            $entry = info(NepaliDate::parse($text));

            self::assertNull($entry->tithi(), $text);
            self::assertFalse($entry->isPublicHoliday(), $text);
            self::assertSame([], $entry->events(), $text);
        }
    }

    public function testTheCoverageIsTheSummaryRange(): void
    {
        $coverage = coverage();

        self::assertSame(Summary::string('first_day'), $coverage->start()->isoformat());
        self::assertSame(Summary::string('last_day'), $coverage->end()->isoformat());
        self::assertSame(Summary::int('first_serial'), $coverage->start()->serial());
        self::assertSame(Summary::int('last_serial'), $coverage->end()->serial());
        self::assertSame(Summary::int('days'), $coverage->count());
    }

    public function testInfoNeverFailsAcrossTheWholeCoverage(): void
    {
        $days = 0;
        foreach (coverage() as $date) {
            $entry = info($date);
            self::assertInstanceOf(CalendarInfo::class, $entry);
            self::assertSame($date->serial(), $date->serial());
            $days++;
        }

        self::assertSame(Summary::int('days'), $days);
    }

    public function testTheCountsMatchTheSummary(): void
    {
        $tithiDays = 0;
        $holidays = 0;
        $eventDays = 0;
        $events = 0;
        $names = [];

        foreach (coverage() as $date) {
            $entry = info($date);
            $tithi = $entry->tithi();
            if ($tithi !== null) {
                $tithiDays++;
                $names[$tithi->np . "\x00" . $tithi->en] = true;
            }
            if ($entry->isPublicHoliday()) {
                $holidays++;
            }
            if ($entry->events() !== []) {
                $eventDays++;
                foreach ($entry->events() as $event) {
                    $events++;
                    $names[$event->np . "\x00" . $event->en] = true;
                }
            }
        }

        self::assertSame(Summary::int('tithi_days'), $tithiDays);
        self::assertSame(Summary::int('holidays'), $holidays);
        self::assertSame(Summary::int('event_days'), $eventDays);
        self::assertSame(Summary::int('events'), $events);
        self::assertSame(Summary::int('names'), count($names));
    }

    public function testTheTithiRangeIsContiguous(): void
    {
        $first = NepaliDate::parse(Summary::string('tithi_first_day'));
        $last = NepaliDate::parse(Summary::string('tithi_last_day'));
        self::assertSame(Summary::int('tithi_first_serial'), $first->serial());

        $range = NepaliDateRange::of($first, $last);
        self::assertSame(Summary::int('tithi_days'), $range->count());
        foreach ($range as $date) {
            self::assertNotNull(info($date)->tithi(), $date->isoformat());
        }
    }

    public function testTheStarredTithiNamesAreVerbatim(): void
    {
        // U-09: the marker is upstream's, and a port shows the name as it is in the data.
        $starred = [];
        foreach (coverage() as $date) {
            $tithi = info($date)->tithi();
            if ($tithi !== null && str_ends_with($tithi->np, '*')) {
                $starred[] = $tithi;
            }
        }

        self::assertNotSame([], $starred);
        foreach ($starred as $tithi) {
            self::assertStringEndsWith('(K)', $tithi->en);
            self::assertStringNotContainsString('*', $tithi->en);
        }
    }

    public function testPublicHolidaysAreAscendingAndFlaggedByInfo(): void
    {
        $holidays = publicHolidays();

        self::assertCount(Summary::int('holidays'), $holidays);
        self::assertTrue($holidays[0]->equals(NepaliDate::parse('2077-01-01')));
        $previous = null;
        foreach ($holidays as $holiday) {
            self::assertTrue(info($holiday)->isPublicHoliday(), $holiday->isoformat());
            if ($previous !== null) {
                self::assertGreaterThan(0, $previous->daysUntil($holiday));
            }
            $previous = $holiday;
        }
    }

    public function testANameFollowsTheLanguage(): void
    {
        $entry = info(NepaliDate::parse('2081-01-01'));

        $tithi = $entry->tithi();
        self::assertNotNull($tithi);
        self::assertSame('Panchami', $tithi->name());
        self::assertSame('पञ्चमी', $tithi->name(Lang::Ne));
        self::assertSame('पञ्चमी', $tithi->np);
        self::assertSame('Panchami', $tithi->en);

        $events = $entry->events();
        self::assertCount(3, $events);
        self::assertSame('New Year', $events[0]->name());
        self::assertSame('नयाँ वर्ष', $events[0]->name(Lang::Ne));
    }

    public function testTheEventOrderIsTheFileOrder(): void
    {
        $texts = array_map(
            static fn (NepEvent $event): string => $event->name(),
            info(NepaliDate::parse('2080-11-01'))->events(),
        );

        self::assertSame([
            'Mangala Chauthi Brata',
            "People's War Day",
            'Tilakund Chauthi',
            'Kumbha Sankranti',
            'World Radio Day',
        ], $texts);
    }

    public function testTheValueTypes(): void
    {
        $tithi = new Tithi('पञ्चमी', 'Panchami');
        $event = new NepEvent('नयाँ वर्ष', 'New Year');
        $entry = new CalendarInfo($tithi, true, [$event]);

        self::assertSame($tithi, $entry->tithi());
        self::assertTrue($entry->isPublicHoliday());
        self::assertSame([$event], $entry->events());
        self::assertNull((new CalendarInfo(null, false, []))->tithi());
    }

    /**
     * @param list<string> $events
     */
    private function assertDay(string $text, string $tithi, bool $holiday, array $events): void
    {
        $entry = info(NepaliDate::parse($text));

        $actualTithi = $entry->tithi();
        self::assertNotNull($actualTithi, $text);
        self::assertSame($tithi, $actualTithi->en, $text);
        self::assertSame($holiday, $entry->isPublicHoliday(), $text);
        self::assertSame(
            $events,
            array_map(static fn (NepEvent $event): string => $event->en, $entry->events()),
            $text,
        );
    }
}
