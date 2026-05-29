<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;

/**
 * The default {@see Filesystem}, backed by native PHP filesystem functions.
 */
final class NativeFilesystem implements Filesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function readable(string $path): bool
    {
        return is_readable($path);
    }

    public function temporaryPath(string $extension): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'parsel_'.uniqid('', true).'.'.$extension;
    }

    public function put(string $path, string $contents): void
    {
        set_error_handler(static fn (): bool => true);
        $written = file_put_contents($path, $contents);
        restore_error_handler();

        if ($written === false) {
            throw FilesystemException::unableToWrite($path);
        }
    }

    public function delete(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function files(string $directory): array
    {
        $glob = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*');
        $files = $glob === false ? [] : array_values(array_filter($glob, is_file(...)));

        sort($files);

        return $files;
    }
}
