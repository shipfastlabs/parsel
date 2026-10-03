<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Support\ProcessResult;

it('reports a successful exit code', function (): void {
    expect(new ProcessResult(0, 'out', '', ['lit'])->successful())->toBeTrue();
});

it('reports a failing exit code', function (): void {
    expect(new ProcessResult(2, '', 'err', ['lit'])->successful())->toBeFalse();
});

it('reports a timed out process as unsuccessful', function (): void {
    $result = new ProcessResult(0, '', '', ['lit'], timedOutAfter: 1.5);

    expect($result->timedOut())->toBeTrue()
        ->and($result->successful())->toBeFalse();
});
