<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\ParselManager;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * The default {@see ProcessRunner}, backed by Symfony Process.
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, ?string $input = null, ?float $timeout = ParselManager::DEFAULT_TIMEOUT): ProcessResult
    {
        $process = new Process($command, null, null, $input, $timeout);
        $timedOutAfter = null;

        try {
            $process->run();
        } catch (ProcessTimedOutException $processTimedOutException) {
            $timedOutAfter = $processTimedOutException->getExceededTimeout();
        }

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            command: $command,
            timedOutAfter: $timedOutAfter,
        );
    }
}
