<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Shipfastlabs\Parsel\Contracts\ExecutableFinder;

final readonly class FakeExecutableFinder implements ExecutableFinder
{
    public function __construct(
        private ?string $result = null,
    ) {}

    public function find(string $name): ?string
    {
        return $this->result;
    }
}
