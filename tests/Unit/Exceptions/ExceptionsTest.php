<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\Exceptions\InvalidOutputException;
use Shipfastlabs\Parsel\Exceptions\OcrRequiredException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\ParserUsageException;
use Shipfastlabs\Parsel\Support\ProcessResult;

it('builds a parse failure from a result with stderr', function (): void {
    $exception = ParseFailedException::fromResult(new ProcessResult(2, '', 'boom', ['lit', 'parse']));

    expect($exception->exitCode)->toBe(2)
        ->and($exception->stderr)->toBe('boom')
        ->and($exception->command)->toBe(['lit', 'parse'])
        ->and($exception->getMessage())->toContain('code 2')->toContain('boom');
});

it('uses a placeholder when there is no stderr', function (): void {
    expect(ParseFailedException::fromResult(new ProcessResult(1, '', '', ['lit']))->getMessage())
        ->toContain('(no error output)');
});

it('builds specific parse failures that remain parse failures', function (): void {
    $usage = ParserUsageException::fromResult(new ProcessResult(2, '', "anydoc: missing input\n", ['anydoc']), 'anydoc');
    $ocr = OcrRequiredException::fromResult(new ProcessResult(3, '', '', ['anydoc']), 'anydoc');

    expect($usage)->toBeInstanceOf(ParserUsageException::class)->toBeInstanceOf(ParseFailedException::class)
        ->and($usage->getMessage())->toBe('anydoc exited with code 2: anydoc: missing input')
        ->and($ocr)->toBeInstanceOf(OcrRequiredException::class)->toBeInstanceOf(ParseFailedException::class)
        ->and($ocr->getMessage())->toStartWith('anydoc exited with code 3: (no error output). Scanned')
        ->toContain("option('ocr', 'hosted')")->toContain('withOcr()');
});

it('describes invalid output', function (): void {
    expect(InvalidOutputException::emptyOutput()->getMessage())->toContain('empty output')
        ->and(InvalidOutputException::malformedJson('detail here')->getMessage())->toContain('detail here');
});

it('describes a missing binary', function (): void {
    expect(BinaryNotFoundException::onPath('lit', 'PARSEL_LIT_BINARY')->getMessage())
        ->toContain('lit')->toContain('PARSEL_LIT_BINARY');
});
