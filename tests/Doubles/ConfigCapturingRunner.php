<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;

final class ConfigCapturingRunner implements ProcessRunner
{
    public ?string $configPath = null;

    public ?string $configContents = null;

    public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
    {
        $config = array_search('--config', $command, true);

        if ($config !== false) {
            $this->configPath = $command[$config + 1];
            $this->configContents = (string) file_get_contents($this->configPath);
        }

        $output = array_search('-o', $command, true);

        if ($output !== false) {
            file_put_contents($command[$output + 1], '{"pages":[]}');
        }

        return new ProcessResult(0, 'ok', '', $command);
    }
}
