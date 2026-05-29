<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;

final readonly class FakeJsonOutputRunner implements ProcessRunner
{
    public function __construct(
        private string $json = '',
        private int $exitCode = 0,
    ) {}

    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
    {
        $index = array_search('-o', $command, true);

        if ($this->exitCode === 0 && $index !== false) {
            file_put_contents($command[$index + 1], $this->json);
        }

        return new ProcessResult($this->exitCode, '', $this->exitCode === 0 ? '' : 'boom', $command);
    }
}
