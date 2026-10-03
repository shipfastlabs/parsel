<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;

final readonly class FakeScreenshotRunner implements ProcessRunner
{
    /**
     * @param  list<int>  $pages
     */
    public function __construct(
        private array $pages,
        private ?int $modifiedAt = null,
    ) {}

    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
    {
        $index = array_search('-o', $command, true);
        $directory = $index === false ? sys_get_temp_dir() : $command[$index + 1];

        foreach ($this->pages as $page) {
            $path = $directory.DIRECTORY_SEPARATOR.'page_'.$page.'.png';
            file_put_contents($path, 'png');

            if ($this->modifiedAt !== null) {
                touch($path, $this->modifiedAt);
            }
        }

        return new ProcessResult(0, '', '', $command);
    }
}
