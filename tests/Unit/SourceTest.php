<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Exceptions\SourceNotFoundException;
use Shipfastlabs\Parsel\Exceptions\SourceNotReadableException;
use Shipfastlabs\Parsel\Exceptions\UrlDownloadException;
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

it('rejects a whitespace-only extension for byte sources', function (): void {
    Source::fromBytes('data', '   ');
})->throws(InvalidArgumentException::class);

it('rejects a tab-only extension for byte sources', function (): void {
    Source::fromBytes('data', "\t");
})->throws(InvalidArgumentException::class);

it('builds from a URL and lowercases the extension', function (): void {
    $source = Source::fromUrl('https://example.com/report.PDF');

    expect($source->extension)->toBe('pdf')
        ->and($source->isUrl())->toBeTrue()
        ->and($source->isBytes())->toBeFalse()
        ->and($source->url())->toBe('https://example.com/report.PDF');
});

it('rejects a URL with a non-http scheme', function (): void {
    Source::fromUrl('ftp://example.com/file.pdf');
})->throws(InvalidArgumentException::class);

it('rejects a URL without a file extension', function (): void {
    Source::fromUrl('https://example.com/document');
})->throws(InvalidArgumentException::class);

it('rejects a string that is not a valid URL', function (): void {
    Source::fromUrl('not-a-url');
})->throws(InvalidArgumentException::class);
