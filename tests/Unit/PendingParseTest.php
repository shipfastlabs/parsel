<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\UnsupportedCapabilityException;
use Shipfastlabs\Parsel\Options\AnyDocOptions;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;

it('accepts strict arrays and merges repeated provider options', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)
        ->withProviderOptions(['pages' => '1-2', 'extra' => ['alpha' => true]])
        ->withProviderOptions(['pages' => '5', 'extra' => ['beta' => 2]])
        ->text();

    expect($fake->recordedCommands()[0])->toContain('--target-pages', '1-2,5', '--alpha', '--beta', '2');
});

it('merges repeated screenshot extra options', function (): void {
    $fake = new FakeProcessRunner(['screenshot' => '']);
    $directory = sys_get_temp_dir();

    fakeParse($fake)
        ->withProviderOptions(['screenshot_extra' => ['alpha' => true]])
        ->withProviderOptions(['screenshot_extra' => ['beta' => 2]])
        ->screenshots($directory);

    expect($fake->recordedCommands()[0])->toContain('--alpha', '--beta', '2');
});

it('rejects unknown and cross-provider options before parsing', function (): void {
    expect(fn (): PendingParse => Parsel::file('a.pdf')->withProviderOptions(['typo' => true]))
        ->toThrow(InvalidProviderOptionsException::class, 'typo')
        ->and(fn (): PendingParse => Parsel::file('a.pdf')->withProviderOptions(AnyDocOptions::make()))
        ->toThrow(InvalidProviderOptionsException::class, 'anydoc');
});

it('saves output according to the destination extension', function (): void {
    Parsel::fake([
        '--format text' => 'plain',
        '--format markdown' => '# Markdown',
        '--format json' => fixtureContents('liteparse-output.json'),
    ]);
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_save_'.uniqid();

    expect(Parsel::file(fixture('sample.pdf'))->save($base.'.txt'))->toBe($base.'.txt')
        ->and(Parsel::file(fixture('sample.pdf'))->save($base.'.md'))->toBe($base.'.md')
        ->and(Parsel::file(fixture('sample.pdf'))->save($base.'.json'))->toBe($base.'.json')
        ->and(file_get_contents($base.'.json'))->toContain('textItems');

    unlink($base.'.txt');
    unlink($base.'.md');
    unlink($base.'.json');
});

it('rejects unsupported anydoc capabilities before executing the driver', function (string $method, array $arguments): void {
    $fake = Parsel::fake();

    expect(fn () => Parsel::driver('anydoc')->file('missing.docx')->{$method}(...$arguments))
        ->toThrow(UnsupportedCapabilityException::class)
        ->and($fake->ranCount())->toBe(0);
})->with([
    'text' => ['text', []],
    'structured document' => ['parse', []],
    'array' => ['toArray', []],
    'screenshots' => ['screenshots', ['/tmp']],
]);

it('rejects unsupported generators and save formats lazily', function (): void {
    Parsel::fake();

    expect(fn (): array => iterator_to_array(Parsel::driver('anydoc')->file('missing.docx')->lazyPages()))
        ->toThrow(UnsupportedCapabilityException::class, 'lazy pages')
        ->and(fn (): string => Parsel::driver('anydoc')->file('missing.docx')->save('/tmp/output.json'))
        ->toThrow(UnsupportedCapabilityException::class, 'JSON');
});
