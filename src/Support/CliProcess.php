<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use SensitiveParameter;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\ParseTimedOutException;
use Shipfastlabs\Parsel\Source;

final readonly class CliProcess
{
    public function __construct(
        private ProcessRunner $process = new SymfonyProcessRunner,
        private Filesystem $files = new NativeFilesystem,
    ) {}

    /**
     * @param  callable(string): list<string>  $command
     * @param  array<int, class-string<ParseFailedException>>  $failures  Exception classes keyed by exit code.
     */
    public function run(Source $source, #[SensitiveParameter] callable $command, ?float $timeout, string $driver, array $failures = []): ProcessResult
    {
        [$file, $temporary] = $this->resolveFile($source);

        try {
            $result = $this->process->run($command($file), null, $timeout);
        } finally {
            if ($temporary !== null) {
                $this->files->delete($temporary);
            }
        }

        if ($result->timedOut()) {
            throw ParseTimedOutException::fromResult($result, $driver);
        }

        if (! $result->successful()) {
            $exception = $failures[$result->exitCode] ?? ParseFailedException::class;

            throw $exception::fromResult($result, $driver);
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
