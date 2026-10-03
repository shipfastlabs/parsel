<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;

/**
 * @internal
 */
final readonly class StagingDirectory
{
    public function __construct(
        private Filesystem $files = new NativeFilesystem,
    ) {}

    public function create(): string
    {
        $path = $this->files->temporaryPath('d');

        if (! $this->quietly(static fn (): bool => mkdir($path, 0700))) {
            throw FilesystemException::unableToWrite($path);
        }

        return $path;
    }

    public function move(string $from, string $to): void
    {
        if (! $this->quietly(static fn (): bool => rename($from, $to))) {
            throw FilesystemException::unableToWrite($to);
        }
    }

    public function delete(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            is_dir($path) && ! is_link($path) ? $this->delete($path) : $this->files->delete($path);
        }

        rmdir($directory);
    }

    /** @param callable(): bool $operation */
    private function quietly(callable $operation): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
