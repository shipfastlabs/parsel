<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Support\SymfonyProcessRunner;

it('runs a command and captures stdout', function (): void {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'fwrite(STDOUT, "hi");']);

    expect($result->stdout)->toBe('hi')
        ->and($result->exitCode)->toBe(0)
        ->and($result->successful())->toBeTrue();
});

it('captures a non-zero exit code and stderr', function (): void {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(3);']);

    expect($result->exitCode)->toBe(3)
        ->and($result->stderr)->toContain('boom');
});

it('pipes input to the process stdin', function (): void {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'], 'piped-in');

    expect($result->stdout)->toBe('piped-in');
});

it('reports a timed out process instead of throwing', function (): void {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'sleep(5);'], null, 0.2);

    expect($result->timedOut())->toBeTrue()
        ->and($result->timedOutAfter)->toBe(0.2)
        ->and($result->successful())->toBeFalse()
        ->and($result->command)->toBe([PHP_BINARY, '-r', 'sleep(5);']);
});

it('does not flag a process that finishes within its timeout', function (): void {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'echo "ok";'], null, 10.0);

    expect($result->timedOut())->toBeFalse()
        ->and($result->timedOutAfter)->toBeNull();
});
