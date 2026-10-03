<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\BatchRequest;

/**
 * @internal
 */
final class BatchOutputs
{
    /** @return list<string> */
    public static function written(BatchRequest $request, string $extension): array
    {
        $outputs = [];

        foreach (self::inputs($request->inputDirectory, $request->recursive, $request->extension) as $relative) {
            $directory = dirname($relative);
            $output = rtrim($request->outputDirectory, '/\\')
                .DIRECTORY_SEPARATOR
                .($directory === '.' ? '' : $directory.DIRECTORY_SEPARATOR)
                .pathinfo($relative, PATHINFO_FILENAME).'.'.$extension;

            if (is_file($output)) {
                $outputs[$output] = true;
            }
        }

        $outputs = array_keys($outputs);
        sort($outputs);

        return $outputs;
    }

    /** @return list<string> Paths relative to the input root. */
    private static function inputs(string $root, bool $recursive, ?string $extension, string $prefix = ''): array
    {
        $entries = scandir($root.DIRECTORY_SEPARATOR.$prefix) ?: [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $relative = $prefix.$entry;
            $path = $root.DIRECTORY_SEPARATOR.$relative;

            if (is_dir($path)) {
                if ($recursive) {
                    array_push($files, ...self::inputs($root, true, $extension, $relative.DIRECTORY_SEPARATOR));
                }

                continue;
            }

            if ($extension === null || str_ends_with(strtolower($entry), '.'.$extension)) {
                $files[] = $relative;
            }
        }

        return $files;
    }
}
