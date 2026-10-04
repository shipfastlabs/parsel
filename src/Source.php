<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use InvalidArgumentException;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Exceptions\SourceNotFoundException;
use Shipfastlabs\Parsel\Exceptions\SourceNotReadableException;

final readonly class Source
{
    private function __construct(
        public string $extension,
        private ?string $filePath,
        private ?string $contents,
    ) {}

    public static function fromPath(string $path): self
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('A non-empty file path is required.');
        }

        return new self(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $path, null);
    }

    public static function fromBytes(string $contents, string $extension): self
    {
        $normalized = strtolower(ltrim(trim($extension), '.'));

        if ($normalized === '') {
            throw new InvalidArgumentException('A non-empty file extension is required for byte sources so the selected driver can detect the format.');
        }

        if (preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $normalized) !== 1) {
            throw new InvalidArgumentException('The file extension may only contain letters, digits and dots.');
        }

        return new self($normalized, null, $contents);
    }

    public function isBytes(): bool
    {
        return $this->contents !== null;
    }

    public function contents(): string
    {
        return $this->contents ?? '';
    }

    public function path(): ?string
    {
        return $this->filePath;
    }

    public function validatedPath(Filesystem $files): string
    {
        $path = $this->filePath ?? '';

        if (! $files->exists($path)) {
            throw SourceNotFoundException::forPath($path);
        }

        if (! $files->readable($path)) {
            throw SourceNotReadableException::forPath($path);
        }

        return $path;
    }
}
