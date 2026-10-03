<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;

final class FakeScreenshotRunner implements ProcessRunner
{
    public ?string $outputDirectory = null;

    /**
     * @param  list<string>  $files
     */
    public function __construct(
        private readonly array $files,
        private readonly int $exitCode = 0,
    ) {}

    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
    {
        $index = (int) array_search('-o', $command, true);
        $this->outputDirectory = $command[$index + 1];

        foreach ($this->files as $file) {
            file_put_contents($this->outputDirectory.DIRECTORY_SEPARATOR.$file, 'png');
        }

        return new ProcessResult($this->exitCode, '', $this->exitCode === 0 ? '' : 'boom', $command);
    }
}
