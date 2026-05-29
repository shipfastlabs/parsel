<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Contracts;

use Shipfastlabs\Parsel\Support\ProcessResult;

interface ProcessRunner
{
    /**
     * @param  list<string>  $command  Full argv; element 0 is the binary.
     * @param  string|null  $input  Data to pipe to the process' stdin.
     */
    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult;
}
