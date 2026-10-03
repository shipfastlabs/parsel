<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

final readonly class ProcessResult
{
    /**
     * @param  list<string>  $command
     * @param  float|null  $timedOutAfter  The timeout (in seconds) the process exceeded, or null when it did not time out.
     */
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public array $command,
        public ?float $timedOutAfter = null,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0 && ! $this->timedOut();
    }

    public function timedOut(): bool
    {
        return $this->timedOutAfter !== null;
    }
}
