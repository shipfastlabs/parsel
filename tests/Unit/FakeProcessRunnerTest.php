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
        ->and($fake->recordedCommands())->toBe([['lit', 'parse', 'a'], ['lit', 'screenshot', 'b']])
        ->and($fake->recordedInputs())->toBe(['stdin', null]);
});

it('prefers the longest matching needle', function (): void {
    $fake = new FakeProcessRunner([
        'parse file.pdf' => 'specific',
        'parse' => 'generic',
    ]);

    expect($fake->run(['lit', 'parse', 'file.pdf'])->stdout)->toBe('specific');
});

it('writes a string response to the file named by -o so file based drivers can be faked', function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_fake_'.uniqid('', true).'.json';

    new FakeProcessRunner(['parse' => '{"pages":[]}'])->run(['lit', 'parse', 'a.pdf', '-o', $path]);

    expect(file_get_contents($path))->toBe('{"pages":[]}');

    unlink($path);
});

it('does not write when -o is a directory, has no value or points into a missing directory', function (): void {
    $fake = new FakeProcessRunner(['lit' => 'out']);
    $fake->run(['lit', '-o', sys_get_temp_dir()]);
    $fake->run(['lit', '-o']);
    $fake->run(['lit', '-o', '/missing/parsel/dir/out.json']);

    expect(file_exists('/missing/parsel/dir/out.json'))->toBeFalse()
        ->and($fake->ranCount())->toBe(3);
});
