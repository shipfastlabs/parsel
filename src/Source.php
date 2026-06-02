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
        private ?string $remoteUrl = null,
    ) {}

    public static function fromPath(string $path): self
    {
        return new self(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $path, null);
    }

    public static function fromUrl(string $url): self
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The URL must be a valid HTTP or HTTPS URL.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The URL must use the http or https scheme.');
        }

        $urlPath = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        if ($extension === '') {
            throw new InvalidArgumentException('The URL must include a file extension so liteparse can detect the format.');
        }

        return new self($extension, null, null, $url);
    }

    public static function fromBytes(string $contents, string $extension): self
    {
        $normalized = strtolower(ltrim(trim($extension), '.'));

        if ($normalized === '') {
            throw new InvalidArgumentException('A non-empty file extension is required for byte sources so liteparse can detect the format.');
        }

        return new self($normalized, null, $contents);
    }

    public function isUrl(): bool
    {
        return $this->remoteUrl !== null;
    }

    public function url(): string
    {
        return $this->remoteUrl ?? '';
    }

    public function isBytes(): bool
    {
        return $this->contents !== null;
    }

    public function contents(): string
    {
        return $this->contents ?? '';
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
