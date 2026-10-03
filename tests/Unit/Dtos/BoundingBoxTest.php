<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\BoundingBox;

it('maps and serializes a bounding box', function (): void {
    $box = BoundingBox::fromArray(['x' => 1, 'y' => '2.5', 'width' => 3, 'height' => 4]);

    expect($box->x)->toBe(1.0)
        ->and($box->y)->toBe(2.5)
        ->and($box->toArray())->toBe(['x' => 1.0, 'y' => 2.5, 'width' => 3.0, 'height' => 4.0]);
});

it('defaults missing coordinates to zero', function (): void {
    expect(BoundingBox::fromArray([])->toArray())->toBe(['x' => 0.0, 'y' => 0.0, 'width' => 0.0, 'height' => 0.0]);
});
