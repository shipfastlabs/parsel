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
