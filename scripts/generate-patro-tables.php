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

$root = dirname(__DIR__);
$hubRoot = dirname($root, 2); // packages/nepdate-patro -> repository root
$calendarPath = $hubRoot . '/shared/data/calendar/bs-calendar.json';
$patroDir = $hubRoot . '/shared/data/patro';
$summaryPath = $hubRoot . '/shared/spec/vectors/patro-summary.json';
$outPath = $root . '/src/Internal/PatroTable.php';

$calendar = json_decode((string) file_get_contents($calendarPath), true, 512, JSON_THROW_ON_ERROR);
$lengths = $calendar['month_lengths'];

/** @var array<int, array<int, int>> $monthLength [year][month] => length */
$monthLength = [];
$yearStart = [];
$cursor = 0;
foreach ($lengths as $year => $months) {
    $yearStart[(int) $year] = $cursor;
    foreach ($months as $i => $len) {
        $monthLength[(int) $year][$i + 1] = (int) $len;
    }
    $cursor += array_sum($months);
}

function toSerial(int $y, int $m, int $d, array $yearStart, array $monthLength): int
{
    $s = $yearStart[$y];
    for ($k = 1; $k < $m; $k++) {
        $s += $monthLength[$y][$k];
    }
    return $s + ($d - 1);
}

$summary = json_decode((string) file_get_contents($summaryPath), true, 512, JSON_THROW_ON_ERROR);

$files = glob($patroDir . '/*.json');
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
    $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $lastSource = $data['source'] ?? $lastSource;
    foreach ($data['days'] as $dateKey => $day) {
        [$y, $m, $d] = array_map('intval', explode('-', $dateKey));
        $serial = toSerial($y, $m, $d, $yearStart, $monthLength);

        if (isset($day['tithi'])) {
            $tithiBySerial[$serial] = $internName($day['tithi']['np'], $day['tithi']['en']);
        }
        if (!empty($day['holiday'])) {
            $holidaySerials[] = $serial;
        }
        if (!empty($day['events'])) {
            $indices = [];
            foreach ($day['events'] as $event) {
                $indices[] = $internName($event['np'], $event['en']);
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
    array_keys($eventsBySerial)
));
sort($allSerials);
$firstSerial = $allSerials[0];
$lastSerial = $allSerials[count($allSerials) - 1];

$errors = [];
$coverageDays = $lastSerial - $firstSerial + 1; // coverage() span (PATRO.md §2), not entries-with-data
if ($coverageDays !== $summary['days']) {
    $errors[] = sprintf('days: computed %d, summary says %d', $coverageDays, $summary['days']);
}
if ($firstSerial !== $summary['first_serial']) {
    $errors[] = sprintf('first_serial: computed %d, summary says %d', $firstSerial, $summary['first_serial']);
}
if ($lastSerial !== $summary['last_serial']) {
    $errors[] = sprintf('last_serial: computed %d, summary says %d', $lastSerial, $summary['last_serial']);
}
if (count($holidaySerials) !== $summary['holidays']) {
    $errors[] = sprintf('holidays: computed %d, summary says %d', count($holidaySerials), $summary['holidays']);
}
if (count($eventsBySerial) !== $summary['event_days']) {
    $errors[] = sprintf('event_days: computed %d, summary says %d', count($eventsBySerial), $summary['event_days']);
}
$totalEvents = array_sum(array_map('count', $eventsBySerial));
if ($totalEvents !== $summary['events']) {
    $errors[] = sprintf('events: computed %d, summary says %d', $totalEvents, $summary['events']);
}
if (count($tithiBySerial) !== $summary['tithi_days']) {
    $errors[] = sprintf('tithi_days: computed %d, summary says %d', count($tithiBySerial), $summary['tithi_days']);
}

$tithiSerials = array_keys($tithiBySerial);
$tithiFirst = $tithiSerials[0];
$tithiLast = $tithiSerials[count($tithiSerials) - 1];
if ($tithiFirst !== $summary['tithi_first_serial']) {
    $errors[] = sprintf('tithi_first_serial: computed %d, summary says %d', $tithiFirst, $summary['tithi_first_serial']);
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

$namesLiteral = implode(",\n        ", array_map(
    static fn (array $pair): string => '[' . phpStringLiteral($pair[0]) . ', ' . phpStringLiteral($pair[1]) . ']',
    $names
));

$source = $lastSource ?? ['repo' => '', 'commit' => ''];
$generatedFrom = sprintf('%s @ %s', $source['repo'], substr((string) $source['commit'], 0, 12));

$intList = static fn (array $v): string => implode(', ', $v);

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
    public const array TITHI_INDEX = [{$intList($tithiIndexLiteral)}];

    /** @var list<int> Ascending serials with a gazetted public holiday. */
    public const array HOLIDAY_SERIALS = [{$intList($holidaySerials)}];

    /** @var list<int> Ascending serials with one or more events. */
    public const array EVENT_DAY_SERIALS = [{$intList($eventDaySerials)}];

    /** @var list<int> CSR row pointer into EVENTS_FLAT, length count(EVENT_DAY_SERIALS) + 1. */
    public const array EVENT_START = [{$intList($eventStart)}];

    /** @var list<int> Name index per event, file order within a day, indexed by EVENT_START. */
    public const array EVENTS_FLAT = [{$intList($eventsFlat)}];

    /** @var list<array{string, string}> Deduplicated (np, en) pairs shared by tithi and events. */
    public const array NAMES = [
        $namesLiteral,
    ];
}

PHP;

$current = is_file($outPath) ? file_get_contents($outPath) : null;

if (in_array('--check', $argv, true)) {
    if ($current !== $php) {
        fwrite(STDERR, "src/Internal/PatroTable.php is out of date; run: composer codegen\n");
        exit(1);
    }
    fwrite(STDOUT, "src/Internal/PatroTable.php matches shared/data/patro/*.json\n");
    exit(0);
}

if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0777, true);
}
file_put_contents($outPath, $php);
fwrite(STDOUT, sprintf(
    "wrote src/Internal/PatroTable.php (%d names, %d tithi days, %d holidays, %d event days, %d events)\n",
    count($names),
    $tithiDays,
    count($holidaySerials),
    count($eventDaySerials),
    $totalEvents
));
