<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

/**
 * @internal
 */
final class Cast
{
    public static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    public static function pick(array $raw, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($raw[$key])) {
                return $raw[$key];
            }
        }

        return null;
    }
}
