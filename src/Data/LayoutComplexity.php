<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

final readonly class LayoutComplexity
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $columnCount,
        public int $ruledTableCount,
        public float $ruledTableCoverage,
        public int $textTableRunCount,
        public int $figureCount,
        public float $figureCoverage,
        public bool $isComplex,
        public array $reasons,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            columnCount: Cast::int($raw['columnCount'] ?? 0),
            ruledTableCount: Cast::int($raw['ruledTableCount'] ?? 0),
            ruledTableCoverage: Cast::float($raw['ruledTableCoverage'] ?? 0),
            textTableRunCount: Cast::int($raw['textTableRunCount'] ?? 0),
            figureCount: Cast::int($raw['figureCount'] ?? 0),
            figureCoverage: Cast::float($raw['figureCoverage'] ?? 0),
            isComplex: Cast::bool($raw['isComplex'] ?? false),
            reasons: Cast::strings($raw['reasons'] ?? []),
        );
    }

    /**
     * @return array{
     *     column_count: int,
     *     ruled_table_count: int,
     *     ruled_table_coverage: float,
     *     text_table_run_count: int,
     *     figure_count: int,
     *     figure_coverage: float,
     *     is_complex: bool,
     *     reasons: list<string>,
     * }
     */
    public function toArray(): array
    {
        return [
            'column_count' => $this->columnCount,
            'ruled_table_count' => $this->ruledTableCount,
            'ruled_table_coverage' => $this->ruledTableCoverage,
            'text_table_run_count' => $this->textTableRunCount,
            'figure_count' => $this->figureCount,
            'figure_coverage' => $this->figureCoverage,
            'is_complex' => $this->isComplex,
            'reasons' => $this->reasons,
        ];
    }
}
