<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Exceptions\UrlDownloadException;

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

        try {
            $written = file_put_contents($path, $contents);
        } finally {
            restore_error_handler();
        }

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

    public function download(string $url, ?float $timeout = null): string
    {
        $context = stream_context_create(['http' => ['timeout' => $timeout ?? 30.0]]);

        set_error_handler(static fn (): bool => true);

        try {
            $contents = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($contents === false) {
            throw UrlDownloadException::failedToDownload($url);
        }

        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\S+ (\d{3})/', $http_response_header[0], $matches);
            $statusCode = (int) ($matches[1] ?? 200);

            if ($statusCode >= 400) {
                throw UrlDownloadException::httpError($url, $statusCode);
            }
        }

        return $contents;
    }
}
