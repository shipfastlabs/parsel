<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

use SensitiveParameter;
use Shipfastlabs\Parsel\Support\CommandRedactor;
use Shipfastlabs\Parsel\Support\ProcessResult;

final class ParseFailedException extends ParselException
{
    /**
     * @param  list<string>  $command  The argv that was run, with secret flag values (e.g. `--password`) masked.
     */
    private function __construct(
        string $message,
        public readonly int $exitCode,
        public readonly string $stderr,
        public readonly array $command,
    ) {
        parent::__construct($message);
    }

    public static function fromResult(#[SensitiveParameter] ProcessResult $result, string $driver = 'parser'): self
    {
        $detail = $result->stderr === '' ? '(no error output)' : $result->stderr;

        return new self(
            sprintf('%s exited with code %d: %s', $driver, $result->exitCode, $detail),
            $result->exitCode,
            $result->stderr,
            CommandRedactor::redact($result->command),
        );
    }
}
