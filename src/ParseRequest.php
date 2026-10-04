<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

final readonly class ParseRequest
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public Source $source,
        public array $options = [],
        public ?float $timeout = ParselManager::DEFAULT_TIMEOUT,
    ) {}
}
