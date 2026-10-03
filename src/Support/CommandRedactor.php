<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

/**
 * @internal
 */
final class CommandRedactor
{
    public const array SENSITIVE_FLAGS = [
        '--password',
        '--api-key',
        '--ocr-server-header',
    ];

    public const string MASK = '********';

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    public static function redact(array $command): array
    {
        $redacted = [];
        $maskNext = false;

        foreach ($command as $argument) {
            if ($maskNext) {
                $redacted[] = self::MASK;
                $maskNext = false;

                continue;
            }

            if (in_array($argument, self::SENSITIVE_FLAGS, true)) {
                $redacted[] = $argument;
                $maskNext = true;

                continue;
            }

            $redacted[] = self::redactInline($argument);
        }

        return $redacted;
    }

    private static function redactInline(string $argument): string
    {
        foreach (self::SENSITIVE_FLAGS as $flag) {
            if (str_starts_with($argument, $flag.'=')) {
                return $flag.'='.self::MASK;
            }
        }

        return $argument;
    }
}
