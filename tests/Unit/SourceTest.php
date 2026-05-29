<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Exceptions\SourceNotFoundException;
use Shipfastlabs\Parsel\Exceptions\SourceNotReadableException;
use Shipfastlabs\Parsel\Source;
use Tests\Doubles\FakeFilesystem;

it('builds from a path and lowercases the extension', function (): void {
    $source = Source::fromPath('/docs/report.PDF');

    expect($source->extension)->toBe('pdf')
        ->and($source->isBytes())->toBeFalse();
});

it('builds from raw bytes', function (): void {
    $source = Source::fromBytes('rawdata', '.PNG');

    expect($source->extension)->toBe('png')
        ->and($source->isBytes())->toBeTrue()
        ->and($source->contents())->toBe('rawdata');
});

it('returns the path when it exists and is readable', function (): void {
    expect(Source::fromPath('/docs/report.pdf')->validatedPath(new FakeFilesystem(exists: true, readable: true)))
        ->toBe('/docs/report.pdf');
});

it('throws when the path is missing', function (): void {
    Source::fromPath('/missing.pdf')->validatedPath(new FakeFilesystem(exists: false));
})->throws(SourceNotFoundException::class);

it('throws when the path is not readable', function (): void {
    Source::fromPath('/locked.pdf')->validatedPath(new FakeFilesystem(exists: true, readable: false));
})->throws(SourceNotReadableException::class);

it('rejects an empty extension for byte sources', function (): void {
    Source::fromBytes('data', '.');
})->throws(InvalidArgumentException::class);
