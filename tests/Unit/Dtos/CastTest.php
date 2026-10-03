<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\Cast;

it('casts to string only for strings', function (): void {
    expect(Cast::str('hello'))->toBe('hello')
        ->and(Cast::str(123))->toBe('')
        ->and(Cast::str(null))->toBe('');
});

it('casts numeric values to int', function (): void {
    expect(Cast::int('5'))->toBe(5)
        ->and(Cast::int(7))->toBe(7)
        ->and(Cast::int('nope'))->toBe(0);
});

it('casts numeric values to float', function (): void {
    expect(Cast::float('1.5'))->toBe(1.5)
        ->and(Cast::float(2))->toBe(2.0)
        ->and(Cast::float([]))->toBe(0.0);
});
