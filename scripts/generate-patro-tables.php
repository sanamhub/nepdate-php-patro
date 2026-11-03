<?php

declare(strict_types=1);

/**
 * Compiles shared/data/patro/*.json into src/Internal/PatroTable.php (spec/PATRO.md §3).
 * JSON is read only here, never at runtime. Serials are computed from the same
 * shared/data/calendar/bs-calendar.json the core package uses, so this generator never
 * needs the core package installed.
 *
 *   php scripts/generate-patro-tables.php           regenerate
 *   php scripts/generate-patro-tables.php --check   fail if the committed file would change
 */

require dirname(__DIR__, 3) . '/scripts/lib/cli.php';
require dirname(__DIR__, 3) . '/scripts/lib/json.php';

$root = dirname(__DIR__);
$hubRoot = dirname($root, 2); // packages/nepdate-patro -> repository root
$calendarPath = $hubRoot . '/shared/data/calendar/bs-calendar.json';
$patroDir = $hubRoot . '/shared/data/patro';
$summaryPath = $hubRoot . '/shared/spec/vectors/patro-summary.json';
$outPath = $root . '/src/Internal/PatroTable.php';

$calendar = readJsonObject($calendarPath);
$lengths = jsonObject($calendar['month_lengths'] ?? null, 'month_lengths');

/** @var array<int, array<int, int>> $monthLength [year][month] => length */
$monthLength = [];
/** @var array<int, int> $yearStart serial of 1 Baishakh of each year */
$yearStart = [];
$cursor = 0;
foreach ($lengths as $year => $months) {
    $yearNumber = (int) $year;
    $yearStart[$yearNumber] = $cursor;
    $monthLengths = jsonIntList($months, "month_lengths[$year]");
    foreach ($monthLengths as $i => $len) {
        $monthLength[$yearNumber][$i + 1] = $len;
    }
    $cursor += array_sum($monthLengths);
}

/**
 * @param array<int, int> $yearStart
 * @param array<int, array<int, int>> $monthLength
 */
function toSerial(int $y, int $m, int $d, array $yearStart, array $monthLength): int
{
    $s = $yearStart[$y];
    for ($k = 1; $k < $m; $k++) {
        $s += $monthLength[$y][$k];
    }

    return $s + ($d - 1);
}

$summary = readJsonObject($summaryPath);

$files = glob($patroDir . '/*.json');
if ($files === false) {
    fwrite(STDERR, "cannot list $patroDir\n");
    exit(1);
}
sort($files);

/** @var array<string, int> $nameIndex "np\x00en" => index into $names */
$nameIndex = [];
/** @var list<array{string, string}> $names */
$names = [];

/** @var array<int, int> $tithiBySerial */
$tithiBySerial = [];
/** @var list<int> $holidaySerials */
$holidaySerials = [];
/** @var array<int, list<int>> $eventsBySerial serial => list of name indices, file order */
$eventsBySerial = [];

$internName = function (string $np, string $en) use (&$nameIndex, &$names): int {
    $key = $np . "\x00" . $en;
    if (!isset($nameIndex[$key])) {
        $nameIndex[$key] = count($names);
        $names[] = [$np, $en];
    }

    return $nameIndex[$key];
};

$lastSource = null;
foreach ($files as $file) {
    $data = readJsonObject($file);
    if (isset($data['source'])) {
        $lastSource = jsonObject($data['source'], 'source');
    }
    $days = jsonObject($data['days'] ?? null, 'days');
    foreach ($days as $dateKey => $dayValue) {
        $day = jsonObject($dayValue, "days[$dateKey]");
        [$y, $m, $d] = array_map('intval', explode('-', $dateKey));
        $serial = toSerial($y, $m, $d, $yearStart, $monthLength);

        if (isset($day['tithi'])) {
            $tithi = jsonObject($day['tithi'], "days[$dateKey].tithi");
            $tithiBySerial[$serial] = $internName(
                jsonString($tithi['np'] ?? null, "days[$dateKey].tithi.np"),
                jsonString($tithi['en'] ?? null, "days[$dateKey].tithi.en"),
            );
        }
        if (!empty($day['holiday'])) {
            $holidaySerials[] = $serial;
        }
        if (!empty($day['events'])) {
            $indices = [];
            foreach (jsonList($day['events'], "days[$dateKey].events") as $eventValue) {
                $event = jsonObject($eventValue, "days[$dateKey].events[]");
                $indices[] = $internName(
                    jsonString($event['np'] ?? null, "days[$dateKey].events[].np"),
                    jsonString($event['en'] ?? null, "days[$dateKey].events[].en"),
                );
            }
            $eventsBySerial[$serial] = $indices;
        }
    }
}

ksort($tithiBySerial);
sort($holidaySerials);
ksort($eventsBySerial);

// --- Validate against shared/spec/vectors/patro-summary.json (PATRO.md §3) ---

$allSerials = array_unique(array_merge(
    array_keys($tithiBySerial),
    $holidaySerials,
    array_keys($eventsBySerial),
));
sort($allSerials);
$firstSerial = $allSerials[0];
$lastSerial = $allSerials[count($allSerials) - 1];

$errors = [];
$coverageDays = $lastSerial - $firstSerial + 1; // coverage() span (PATRO.md §2), not entries-with-data
if ($coverageDays !== jsonInt($summary['days'] ?? null, 'days')) {
    $errors[] = sprintf('days: computed %d, summary says %d', $coverageDays, jsonInt($summary['days'] ?? null, 'days'));
}
if ($firstSerial !== jsonInt($summary['first_serial'] ?? null, 'first_serial')) {
    $errors[] = sprintf(
        'first_serial: computed %d, summary says %d',
        $firstSerial,
        jsonInt($summary['first_serial'] ?? null, 'first_serial'),
    );
}
if ($lastSerial !== jsonInt($summary['last_serial'] ?? null, 'last_serial')) {
    $errors[] = sprintf(
        'last_serial: computed %d, summary says %d',
        $lastSerial,
        jsonInt($summary['last_serial'] ?? null, 'last_serial'),
    );
}
if (count($holidaySerials) !== jsonInt($summary['holidays'] ?? null, 'holidays')) {
    $errors[] = sprintf(
        'holidays: computed %d, summary says %d',
        count($holidaySerials),
        jsonInt($summary['holidays'] ?? null, 'holidays'),
    );
}
if (count($eventsBySerial) !== jsonInt($summary['event_days'] ?? null, 'event_days')) {
    $errors[] = sprintf(
        'event_days: computed %d, summary says %d',
        count($eventsBySerial),
        jsonInt($summary['event_days'] ?? null, 'event_days'),
    );
}
$totalEvents = array_sum(array_map(count(...), $eventsBySerial));
if ($totalEvents !== jsonInt($summary['events'] ?? null, 'events')) {
    $errors[] = sprintf(
        'events: computed %d, summary says %d',
        $totalEvents,
        jsonInt($summary['events'] ?? null, 'events'),
    );
}
if (count($tithiBySerial) !== jsonInt($summary['tithi_days'] ?? null, 'tithi_days')) {
    $errors[] = sprintf(
        'tithi_days: computed %d, summary says %d',
        count($tithiBySerial),
        jsonInt($summary['tithi_days'] ?? null, 'tithi_days'),
    );
}

$tithiSerials = array_keys($tithiBySerial);
$tithiFirst = $tithiSerials[0];
$tithiLast = $tithiSerials[count($tithiSerials) - 1];
if ($tithiFirst !== jsonInt($summary['tithi_first_serial'] ?? null, 'tithi_first_serial')) {
    $errors[] = sprintf(
        'tithi_first_serial: computed %d, summary says %d',
        $tithiFirst,
        jsonInt($summary['tithi_first_serial'] ?? null, 'tithi_first_serial'),
    );
}
// Tithi is contiguous: one entry for every serial from first to last (PATRO.md §3).
for ($s = $tithiFirst; $s <= $tithiLast; $s++) {
    if (!isset($tithiBySerial[$s])) {
        $errors[] = "tithi range has a gap at serial $s";
        break;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "patro data disagrees with shared/spec/vectors/patro-summary.json:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - $e\n");
    }
    exit(1);
}

// --- Emit ---

$tithiDays = $tithiLast - $tithiFirst + 1;
$tithiIndexLiteral = [];
for ($s = $tithiFirst; $s <= $tithiLast; $s++) {
    $tithiIndexLiteral[] = $tithiBySerial[$s];
}

$eventDaySerials = array_keys($eventsBySerial);
$eventStart = [0];
$eventsFlat = [];
foreach ($eventDaySerials as $serial) {
    foreach ($eventsBySerial[$serial] as $idx) {
        $eventsFlat[] = $idx;
    }
    $eventStart[] = count($eventsFlat);
}

function phpStringLiteral(string $s): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
}

/**
 * @param array{string, string} $pair
 */
function phpNameLiteral(array $pair): string
{
    return '[' . phpStringLiteral($pair[0]) . ', ' . phpStringLiteral($pair[1]) . ']';
}

$namesLiteral = implode(",\n        ", array_map(phpNameLiteral(...), $names));

$source = $lastSource ?? ['repo' => '', 'commit' => ''];
$generatedFrom = sprintf(
    '%s @ %s',
    jsonString($source['repo'] ?? null, 'source.repo'),
    substr(jsonString($source['commit'] ?? null, 'source.commit'), 0, 12),
);

/**
 * @param list<int> $values
 */
function intListLiteral(array $values): string
{
    return implode(', ', $values);
}

$tithiIndexText = intListLiteral($tithiIndexLiteral);
$holidaySerialsText = intListLiteral($holidaySerials);
$eventDaySerialsText = intListLiteral($eventDaySerials);
$eventStartText = intListLiteral($eventStart);
$eventsFlatText = intListLiteral($eventsFlat);

$php = <<<PHP
    <?php

    declare(strict_types=1);

    namespace NepDate\Patro\Internal;

    // DO NOT EDIT. Generated by scripts/generate-patro-tables.php from
    // shared/data/patro/*.json ($generatedFrom), checked against
    // shared/spec/vectors/patro-summary.json. spec/PATRO.md §3 defines the layout.

    /**
     * @internal
     */
    final class PatroTable
    {
        public const int FIRST_SERIAL = $firstSerial;
        public const int LAST_SERIAL = $lastSerial;

        public const int TITHI_FIRST_SERIAL = $tithiFirst;
        public const int TITHI_LAST_SERIAL = $tithiLast;

        /** @var list<int> One name index per day from TITHI_FIRST_SERIAL to TITHI_LAST_SERIAL. */
        public const array TITHI_INDEX = [{$tithiIndexText}];

        /** @var list<int> Ascending serials with a gazetted public holiday. */
        public const array HOLIDAY_SERIALS = [{$holidaySerialsText}];

        /** @var list<int> Ascending serials with one or more events. */
        public const array EVENT_DAY_SERIALS = [{$eventDaySerialsText}];

        /** @var list<int> CSR row pointer into EVENTS_FLAT, length count(EVENT_DAY_SERIALS) + 1. */
        public const array EVENT_START = [{$eventStartText}];

        /** @var list<int> Name index per event, file order within a day, indexed by EVENT_START. */
        public const array EVENTS_FLAT = [{$eventsFlatText}];

        /** @var list<array{string, string}> Deduplicated (np, en) pairs shared by tithi and events. */
        public const array NAMES = [
            $namesLiteral,
        ];
    }

    PHP;

$current = is_file($outPath) ? file_get_contents($outPath) : null;

if (in_array('--check', cliArgs(), true)) {
    if ($current !== $php) {
        fwrite(STDERR, "src/Internal/PatroTable.php is out of date; run: composer codegen\n");
        exit(1);
    }
    fwrite(STDOUT, "src/Internal/PatroTable.php matches shared/data/patro/*.json\n");
    exit(0);
}

if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0o777, true);
}
file_put_contents($outPath, $php);
fwrite(STDOUT, sprintf(
    "wrote src/Internal/PatroTable.php (%d names, %d tithi days, %d holidays, %d event days, %d events)\n",
    count($names),
    $tithiDays,
    count($holidaySerials),
    count($eventDaySerials),
    $totalEvents,
));
