<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Support\StagingDirectory;
use Tests\Doubles\FakeFilesystem;

it('creates, fills and recursively deletes a private directory', function (): void {
    $staging = new StagingDirectory;
    $directory = $staging->create();
    mkdir($directory.DIRECTORY_SEPARATOR.'nested');
    file_put_contents($directory.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'a.txt', 'A');
    file_put_contents($directory.DIRECTORY_SEPARATOR.'b.txt', 'B');

    expect(is_dir($directory))->toBeTrue();

    $staging->delete($directory);

    expect(file_exists($directory))->toBeFalse();

    $staging->delete($directory);
});

it('creates the directory readable only by its owner', function (): void {
    $staging = new StagingDirectory;
    $directory = $staging->create();

    try {
        expect(fileperms($directory) & 0777)->toBe(0700);
    } finally {
        $staging->delete($directory);
    }
})->skipOnWindows();

it('moves files and overwrites existing ones', function (): void {
    $staging = new StagingDirectory;
    $directory = $staging->create();
    file_put_contents($directory.DIRECTORY_SEPARATOR.'from.txt', 'new');
    file_put_contents($directory.DIRECTORY_SEPARATOR.'to.txt', 'old');

    $staging->move($directory.DIRECTORY_SEPARATOR.'from.txt', $directory.DIRECTORY_SEPARATOR.'to.txt');

    expect(file_get_contents($directory.DIRECTORY_SEPARATOR.'to.txt'))->toBe('new')
        ->and(file_exists($directory.DIRECTORY_SEPARATOR.'from.txt'))->toBeFalse();

    $staging->delete($directory);
});

it('throws when the directory cannot be created', function (): void {
    new StagingDirectory(new FakeFilesystem(temporaryDirectory: '/no/such/parsel/root'))->create();
})->throws(FilesystemException::class);

it('throws when a file cannot be moved', function (): void {
    (new StagingDirectory)->move('/no/such/parsel/file.png', '/no/such/parsel/target.png');
})->throws(FilesystemException::class, '/no/such/parsel/target.png');
