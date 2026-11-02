<?php

declare(strict_types=1);

namespace NepDate\Patro;

use NepDate\Lang;

/**
 * The lunar day (tithi) of a BS date (PATRO.md §2).
 */
final class Tithi
{
    public function __construct(
        public readonly string $np,
        public readonly string $en,
    ) {
    }

    /** `$np` for {@see Lang::Ne}, `$en` for {@see Lang::En}. */
    public function name(Lang $lang = Lang::En): string
    {
        return $lang === Lang::Ne ? $this->np : $this->en;
    }
}
