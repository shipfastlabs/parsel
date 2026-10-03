<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Source;

final readonly class CliProcess
{
    public function __construct(
        private ProcessRunner $process = new SymfonyProcessRunner,
        private Filesystem $files = new NativeFilesystem,
    ) {}

    /**
     * @param  callable(string): list<string>  $command
     */
    public function run(Source $source, callable $command, ?float $timeout, string $driver): ProcessResult
    {
        [$file, $temporary] = $this->resolveFile($source);

        try {
            $result = $this->process->run($command($file), null, $timeout);
        } finally {
            if ($temporary !== null) {
                $this->files->delete($temporary);
            }
        }

        return $this->ensureSuccessful($result, $driver);
    }

    /**
     * @param  list<string>  $command
     */
    public function execute(array $command, ?float $timeout, string $driver): ProcessResult
    {
        return $this->ensureSuccessful($this->process->run($command, null, $timeout), $driver);
    }

    private function ensureSuccessful(ProcessResult $result, string $driver): ProcessResult
    {
        if (! $result->successful()) {
            throw ParseFailedException::fromResult($result, $driver);
        }

        return $result;
    }

    /** @return array{0: string, 1: string|null} */
    private function resolveFile(Source $source): array
    {
        if ($source->isBytes()) {
            $temporary = $this->files->temporaryPath($source->extension);
            $this->files->put($temporary, $source->contents());

            return [$temporary, $temporary];
        }

        return [$source->validatedPath($this->files), null];
    }
}
