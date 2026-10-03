<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use Shipfastlabs\Parsel\Enums\OutputFormat;

final readonly class BatchRequest
{
    /**
     * @param  string|null  $extension  Lowercase extension without the leading dot, or null for every supported file.
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $inputDirectory,
        public string $outputDirectory,
        public OutputFormat $format = OutputFormat::Markdown,
        public bool $recursive = false,
        public ?string $extension = null,
        public array $options = [],
        public ?float $timeout = 60.0,
    ) {}
}
