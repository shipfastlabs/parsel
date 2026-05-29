<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

final readonly class ProcessResult
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public array $command,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
