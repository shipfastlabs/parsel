<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

use SensitiveParameter;
use Shipfastlabs\Parsel\Support\CommandRedactor;
use Shipfastlabs\Parsel\Support\ProcessResult;

final class ParseTimedOutException extends ParselException
{
    /**
     * @param  list<string>  $command
     */
    private function __construct(
        string $message,
        public readonly float $timeout,
        public readonly string $driver,
        public readonly array $command,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $command
     */
    public static function after(float $timeout, #[SensitiveParameter] array $command, string $driver = 'parser'): self
    {
        return new self(
            sprintf('%s timed out after %s seconds.', $driver, rtrim(rtrim(sprintf('%.3F', $timeout), '0'), '.')),
            $timeout,
            $driver,
            CommandRedactor::redact($command),
        );
    }

    public static function fromResult(#[SensitiveParameter] ProcessResult $result, string $driver = 'parser'): self
    {
        return self::after($result->timedOutAfter ?? 0.0, $result->command, $driver);
    }
}
