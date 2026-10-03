<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Enums;

enum AnyDocOcrMode: string
{
    case Reject = 'reject';
    case Hosted = 'hosted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
