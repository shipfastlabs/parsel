<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

/**
 * @internal
 */
final class CliArguments
{
    /** @return list<string> */
    public static function command(string ...$parts): array
    {
        return array_values($parts);
    }

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    public static function flag(array $command, string $name, string|int|null $value): array
    {
        if ($value !== null) {
            $command[] = '--'.$name;
            $command[] = (string) $value;
        }

        return $command;
    }

    /** @param array<string, mixed> $options */
    public static function scalar(array $options, string $key): string|int|null
    {
        $value = $options[$key] ?? null;

        return is_string($value) || is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $options */
    public static function string(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public static function appendExtra(array $command, array $options, string $key = 'extra'): array
    {
        $extra = $options[$key] ?? [];

        if (! is_array($extra)) {
            return $command;
        }

        foreach ($extra as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            if ($value === false) {
                continue;
            }

            if (! is_string($value) && ! is_int($value) && $value !== true) {
                continue;
            }

            $command[] = '--'.$name;

            if ($value !== true) {
                $command[] = (string) $value;
            }
        }

        return $command;
    }
}
