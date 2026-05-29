<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Symfony\Component\Process\Process;

/**
 * The default {@see ProcessRunner}, backed by Symfony Process.
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
    {
        $process = new Process($command, null, null, $input, $timeout);
        $process->run();

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            command: $command,
        );
    }
}
