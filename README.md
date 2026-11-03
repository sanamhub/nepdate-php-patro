<p align="center">
  <img src="https://raw.githubusercontent.com/sanamhub/nepdate-php/main/docs/assets/nepdate-php-128.png" width="128" alt="NepDate for PHP logo">
</p>

<h1 align="center">nepdate-patro</h1>

<p align="center">
  <a href="https://packagist.org/packages/sanamhub/nepdate-patro"><img src="https://img.shields.io/packagist/v/sanamhub/nepdate-patro?include_prereleases&label=sanamhub%2Fnepdate-patro&logo=packagist&logoColor=white" alt="sanamhub/nepdate-patro on Packagist"></a>
  <img src="https://img.shields.io/badge/php-%5E8.3-777BB4?logo=php&logoColor=white" alt="PHP ^8.3">
  <a href="https://github.com/sanamhub/nepdate-php/blob/main/packages/nepdate-patro/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue" alt="License: MIT"></a>
</p>

Tithi, gazetted public holidays and events for the Bikram Sambat calendar, as a separate package
from the core [`sanamhub/nepdate`](https://packagist.org/packages/sanamhub/nepdate). It is part of
[nepdate-php](https://github.com/sanamhub/nepdate-php) and the NepDate family; the data comes from
the hub [`sanamhub/nepdate`](https://github.com/sanamhub/nepdate), which derives it from the C#
original, [NepDate](https://github.com/RajuPrasai/NepDate).

> **Status:** 0.2.0-rc.1, a release candidate, on Packagist; the API may change before 0.2.0. The
> data is informational, not an official calendar.

## Install

```sh
composer require sanamhub/nepdate-patro
```

The package depends on `sanamhub/nepdate`, so the core arrives with it. While only the release
candidate exists, ask for it explicitly:

```sh
composer require "sanamhub/nepdate:^0.2@rc" "sanamhub/nepdate-patro:^0.2@rc"
```

## Example

```php
use NepDate\Lang;
use NepDate\NepaliDate;

use function NepDate\Patro\coverage;
use function NepDate\Patro\info;

$info = info(NepaliDate::fromBs(2081, 1, 1));
$info->tithi()->name(); // 'Panchami'
$info->tithi()->name(Lang::Ne); // 'पञ्चमी'
$info->isPublicHoliday(); // true
$info->events()[0]->name(); // 'New Year'
coverage()->start()->isoformat(); // '2077-01-01'
```

`info()` answers one day at a time and never fails: a date outside the coverage, currently BS
2077-01-01 to 2084-08-20, answers with no tithi, no holiday and no events. `publicHolidays()` lists
every gazetted holiday in the data.

## More

| Topic | Where |
|---|---|
| Every public name, with an example | [docs/API.md](https://github.com/sanamhub/nepdate-php/blob/main/docs/API.md) |
| The core package and the full guide | [sanamhub/nepdate-php](https://github.com/sanamhub/nepdate-php#readme) |
| What changed in each version | [CHANGELOG.md](https://github.com/sanamhub/nepdate-php/blob/main/CHANGELOG.md) |
| A wrong date, holiday or tithi | the hub's [data error form](https://github.com/sanamhub/nepdate/issues/new/choose) |

The source lives in `packages/nepdate-patro` of
[sanamhub/nepdate-php](https://github.com/sanamhub/nepdate-php). The
[sanamhub/nepdate-php-patro](https://github.com/sanamhub/nepdate-php-patro) repository that
Packagist reads is a read-only mirror of that folder, filled by the release workflow, so open
issues and pull requests in [sanamhub/nepdate-php](https://github.com/sanamhub/nepdate-php/issues)
instead.

## License

MIT ([LICENSE](https://github.com/sanamhub/nepdate-php/blob/main/packages/nepdate-patro/LICENSE)).
Calendar and patro data from NepDate by Raju Prasai and contributors, MIT
([NOTICE](https://github.com/sanamhub/nepdate-php/blob/main/packages/nepdate-patro/NOTICE)). Not
affiliated with the Government of Nepal.
