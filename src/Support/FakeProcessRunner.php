<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;

/**
 * A test double for {@see ProcessRunner} that returns canned output instead of
 * {@see ProcessResult} is returned verbatim. Commands and stdin are recorded for
 * later inspection. Used in test suites via {@see Parsel::fake()}.
 */
final class FakeProcessRunner implements ProcessRunner
{
    /**
     * @var list<array{command: list<string>, input: string|null}>
     */
    private array $recorded = [];

    /**
     * @param  array<string, ProcessResult|string>  $responses
     */
    public function __construct(
        private readonly array $responses = [],
    ) {}

    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
    {
        $this->recorded[] = ['command' => $command, 'input' => $input];

        $line = implode(' ', $command);

        $match = null;
        $matchLength = -1;

        foreach ($this->responses as $needle => $response) {
            if (str_contains($line, $needle) && strlen($needle) > $matchLength) {
                $match = $response;
                $matchLength = strlen($needle);
            }
        }

        if ($match === null) {
            return new ProcessResult(0, '', '', $command);
        }

        return $match instanceof ProcessResult
            ? $match
            : new ProcessResult(0, $match, '', $command);
    }

    /**
     * @return list<list<string>>
     */
    public function recordedCommands(): array
    {
        return array_map(static fn (array $record): array => $record['command'], $this->recorded);
    }

    public function ranCount(): int
    {
        return count($this->recorded);
    }
}
