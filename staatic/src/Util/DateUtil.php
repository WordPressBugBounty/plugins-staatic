<?php

declare(strict_types=1);

namespace Staatic\WordPress\Util;

use DateTimeImmutable;
use DateTimeInterface;

final class DateUtil
{
    public static function formatSqlDateTime(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function isDateNumHoursAgo(DateTimeInterface $date, int $numHours): bool
    {
        $differenceInHours = ((new DateTimeImmutable())->getTimestamp() - $date->getTimestamp()) / 3600;

        return $differenceInHours > $numHours;
    }
}
