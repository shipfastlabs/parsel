<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\ExecutableFinder;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;

final readonly class BinaryResolver
{
    private const string ENV_VAR = 'PARSEL_LIT_BINARY';

    private const string DEFAULT_NAME = 'lit';

    public function __construct(
        private ExecutableFinder $finder = new SymfonyExecutableFinder,
    ) {}

    public function resolve(?string $explicit = null): string
    {
        if ($explicit !== null) {
            return $explicit;
        }

        $fromEnv = getenv(self::ENV_VAR);

        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $found = $this->finder->find(self::DEFAULT_NAME);

        if ($found !== null) {
            return $found;
        }

        throw BinaryNotFoundException::onPath(self::DEFAULT_NAME, self::ENV_VAR);
    }
}
