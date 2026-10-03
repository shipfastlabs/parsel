<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\DocumentComplexity;
use Shipfastlabs\Parsel\Data\LayoutComplexity;
use Shipfastlabs\Parsel\Data\PageComplexity;
use Shipfastlabs\Parsel\Exceptions\PageNotFoundException;

function complexityFixture(): DocumentComplexity
{
    /** @var list<mixed> $decoded */
    $decoded = json_decode(fixtureContents('liteparse-complexity.json'), true);

    return DocumentComplexity::fromLiteParseJson($decoded);
}

it('hydrates per-page complexity from real is-complex output', function (): void {
    $complexity = complexityFixture();
    $first = $complexity->page(1);

    expect($complexity->pageCount())->toBe(3)
        ->and($first)->toBeInstanceOf(PageComplexity::class)
        ->and($first->needsOcr)->toBeTrue()
        ->and($first->reasons)->toBe(['sparse-text', 'embedded-images'])
        ->and($first->textLength)->toBe(1654)
        ->and($first->textCoverage)->toBeFloat()
        ->and($first->hasSubstantialImages)->toBeTrue()
        ->and($first->imageBlockCount)->toBe(1)
        ->and($first->imageCoverage)->toBeGreaterThan(0.0)
        ->and($first->largestImageCoverage)->toBeGreaterThan(0.0)
        ->and($first->fullPageImage)->toBeFalse()
        ->and($first->isGarbled)->toBeFalse()
        ->and($first->pageArea)->toBe(484704.0)
        ->and($first->uncoveredVectorArea)->toBeNull()
        ->and($first->layout)->toBeInstanceOf(LayoutComplexity::class)
        ->and($first->layout?->columnCount)->toBe(1)
        ->and($first->layout?->ruledTableCount)->toBe(1)
        ->and($first->layout?->ruledTableCoverage)->toBeGreaterThan(0.0)
        ->and($first->layout?->textTableRunCount)->toBe(1)
        ->and($first->layout?->figureCount)->toBe(0)
        ->and($first->layout?->figureCoverage)->toBe(0.0)
        ->and($first->layout?->isComplex)->toBeTrue()
        ->and($first->layout?->reasons)->toBe(['table-likely'])
        ->and($complexity->page(2)->uncoveredVectorArea)->toBe(0.0)
        ->and($complexity->page(2)->needsOcr)->toBeFalse();
});

it('summarises which pages need OCR or have complex layouts', function (): void {
    $complexity = complexityFixture();

    expect($complexity->needsOcr())->toBeTrue()
        ->and($complexity->pagesNeedingOcr())->toBe([1, 3])
        ->and($complexity->hasComplexLayout())->toBeTrue()
        ->and($complexity->pagesWithComplexLayout())->toBe([1, 3]);
});

it('reports simple documents and tolerates missing layout data', function (): void {
    $complexity = DocumentComplexity::fromLiteParseJson(['bad', ['pageNumber' => 4, 'reasons' => 'invalid', 'layout' => 'invalid']]);
    $page = $complexity->page(4);

    expect($complexity->pageCount())->toBe(1)
        ->and($complexity->needsOcr())->toBeFalse()
        ->and($complexity->pagesNeedingOcr())->toBe([])
        ->and($complexity->hasComplexLayout())->toBeFalse()
        ->and($page->layout)->toBeNull()
        ->and($page->hasComplexLayout())->toBeFalse()
        ->and($page->reasons)->toBe([])
        ->and($page->pageArea)->toBe(0.0);
});

it('throws for a page outside the complexity report', function (): void {
    complexityFixture()->page(99);
})->throws(PageNotFoundException::class);

it('converts complexity reports to arrays', function (): void {
    $array = complexityFixture()->toArray();

    expect($array['needs_ocr'])->toBeTrue()
        ->and($array['pages_needing_ocr'])->toBe([1, 3])
        ->and($array['pages'])->toHaveCount(3)
        ->and($array['pages'][0])->toHaveKeys([
            'number', 'needs_ocr', 'reasons', 'text_length', 'text_coverage', 'has_substantial_images',
            'image_block_count', 'image_coverage', 'largest_image_coverage', 'full_page_image', 'is_garbled',
            'page_area', 'uncovered_vector_area', 'layout',
        ])
        ->and($array['pages'][0]['layout'])->toBe([
            'column_count' => 1,
            'ruled_table_count' => 1,
            'ruled_table_coverage' => 0.0766654834151268,
            'text_table_run_count' => 1,
            'figure_count' => 0,
            'figure_coverage' => 0.0,
            'is_complex' => true,
            'reasons' => ['table-likely'],
        ])
        ->and(DocumentComplexity::fromLiteParseJson([['pageNumber' => 1]])->toArray()['pages'][0]['layout'])->toBeNull();
});
