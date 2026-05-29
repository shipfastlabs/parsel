<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;

it('returns canned stdout for a matching substring', function (): void {
    $result = new FakeProcessRunner(['parse' => 'canned output'])->run(['lit', 'parse', 'file.pdf']);

    expect($result->stdout)->toBe('canned output')
        ->and($result->exitCode)->toBe(0);
});

it('returns a canned ProcessResult verbatim', function (): void {
    $canned = new ProcessResult(7, 'o', 'e', ['lit']);

    expect(new FakeProcessRunner(['parse' => $canned])->run(['lit', 'parse']))->toBe($canned);
});

it('returns empty success when nothing matches', function (): void {
    $result = (new FakeProcessRunner)->run(['lit', 'screenshot']);

    expect($result->stdout)->toBe('')
        ->and($result->successful())->toBeTrue();
});

it('records the commands and their count', function (): void {
    $fake = new FakeProcessRunner;
    $fake->run(['lit', 'parse', 'a'], 'stdin');
    $fake->run(['lit', 'screenshot', 'b']);

    expect($fake->ranCount())->toBe(2)
        ->and($fake->recordedCommands())->toBe([['lit', 'parse', 'a'], ['lit', 'screenshot', 'b']]);
});

it('prefers the longest matching needle', function (): void {
    $fake = new FakeProcessRunner([
        'parse file.pdf' => 'specific',
        'parse' => 'generic',
    ]);

    expect($fake->run(['lit', 'parse', 'file.pdf'])->stdout)->toBe('specific');
});
