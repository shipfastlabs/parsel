<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Support\NativeFilesystem;

it('reports existence and readability', function (): void {
    $files = new NativeFilesystem;

    expect($files->exists(fixture('sample.pdf')))->toBeTrue()
        ->and($files->readable(fixture('sample.pdf')))->toBeTrue()
        ->and($files->exists('/no/such/file.pdf'))->toBeFalse();
});

it('generates a temporary path carrying the extension', function (): void {
    expect((new NativeFilesystem)->temporaryPath('json'))->toEndWith('.json');
});

it('writes, lists and deletes files in a directory', function (): void {
    $files = new NativeFilesystem;
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_test_'.uniqid();
    mkdir($directory);

    $first = $directory.DIRECTORY_SEPARATOR.'a.txt';
    $second = $directory.DIRECTORY_SEPARATOR.'b.txt';
    $files->put($first, 'A');
    $files->put($second, 'B');

    expect($files->files($directory))->toBe([$first, $second]);

    $files->delete($first);
    expect($files->files($directory))->toBe([$second]);

    $files->delete($first);
    $files->delete($second);
    rmdir($directory);
});

it('throws when it cannot write', function (): void {
    (new NativeFilesystem)->put('/no/such/directory/file.txt', 'x');
})->throws(FilesystemException::class);
