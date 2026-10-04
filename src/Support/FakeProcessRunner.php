<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\ParselManager;

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

    public function run(array $command, ?string $input = null, ?float $timeout = ParselManager::DEFAULT_TIMEOUT): ProcessResult
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

        if ($match instanceof ProcessResult) {
            return $match;
        }

        $this->writeOutputFile($command, $match);

        return new ProcessResult(0, $match, '', $command);
    }

    /** @param list<string> $command */
    private function writeOutputFile(array $command, string $contents): void
    {
        $flag = array_search('-o', $command, true);
        $path = $flag === false ? null : ($command[$flag + 1] ?? null);

        if ($path !== null && ! is_dir($path) && is_dir(dirname($path))) {
            file_put_contents($path, $contents);
        }
    }

    /**
     * @return list<list<string>>
     */
    public function recordedCommands(): array
    {
        return array_map(static fn (array $record): array => $record['command'], $this->recorded);
    }

    /**
     * @return list<string|null>
     */
    public function recordedInputs(): array
    {
        return array_map(static fn (array $record): ?string => $record['input'], $this->recorded);
    }

    public function ranCount(): int
    {
        return count($this->recorded);
    }
}
