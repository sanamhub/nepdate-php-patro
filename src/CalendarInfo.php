<?php

declare(strict_types=1);

namespace NepDate\Patro;

/**
 * Everything the patro knows about one BS date (PATRO.md §2). `info()` never
 * fails: a date without data returns tithi `null`, `isPublicHoliday()` false and an empty
 * `events()` list.
 */
final class CalendarInfo
{
    /**
     * @param list<NepEvent> $events ordered as in the source data
     */
    public function __construct(
        private readonly ?Tithi $tithi,
        private readonly bool $isPublicHoliday,
        private readonly array $events,
    ) {
    }

    public function tithi(): ?Tithi
    {
        return $this->tithi;
    }

    public function isPublicHoliday(): bool
    {
        return $this->isPublicHoliday;
    }

    /** @return list<NepEvent> */
    public function events(): array
    {
        return $this->events;
    }
}
