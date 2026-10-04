# Parsel

<p align="center">
    <img src="./art/og.png" height="300" alt="Parsel">
</p>

<p align="center">
    <a href="https://github.com/shipfastlabs/parsel/actions"><img alt="Tests" src="https://github.com/shipfastlabs/parsel/actions/workflows/tests.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="Latest Version" src="https://img.shields.io/packagist/v/shipfastlabs/parsel"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="License" src="https://img.shields.io/packagist/l/shipfastlabs/parsel"></a>
</p>

- [Introduction](#introduction)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
    - [Sources](#sources)
    - [Output](#output)
    - [Timeouts](#timeouts)
- [Drivers](#drivers)
    - [Selecting a Driver](#selecting-a-driver)
    - [Capabilities](#capabilities)
    - [Supported Formats](#supported-formats)
    - [Binary Resolution](#binary-resolution)
- [Provider Options](#provider-options)
    - [LiteParse Options](#liteparse-options)
    - [AnyDoc Options](#anydoc-options)
    - [Array Options](#array-options)
- [Handling Errors](#handling-errors)
- [Custom Drivers](#custom-drivers)
- [Testing](#testing)
- [Upgrading](#upgrading)
- [Contributing](#contributing)
- [Credits and License](#credits-and-license)

## Introduction

Parsel provides an expressive PHP API for extracting Markdown, text, and structured data from documents. It ships with drivers for the local [LiteParse](https://github.com/run-llama/liteparse) and [AnyDoc](https://github.com/firecrawl/anydoc) command-line parsers, and you may register your own.

```php
use Shipfastlabs\Parsel;

$markdown = Parsel::file('report.pdf')->markdown();
```

## Installation

Parsel requires PHP 8.4 or greater. You may install it via Composer:

```bash
composer require shipfastlabs/parsel
```

Next, install the parser binary for the driver you plan to use. By default, the installer installs LiteParse:

```bash
vendor/bin/parsel-install
vendor/bin/parsel-install --driver=anydoc
vendor/bin/parsel-install --driver=all
```

You may choose a package manager with `--manager`. LiteParse supports `npm`, `pnpm`, `bun`, `pip`, and `cargo`; AnyDoc supports `npm`, `pnpm`, and `bun` and requires Node.js 20 or greater. LiteParse needs LibreOffice to convert Office, OpenDocument, RTF, and CSV files, which `--with-system-dependencies` installs along with ImageMagick:

```bash
vendor/bin/parsel-install --driver=anydoc --manager=pnpm
vendor/bin/parsel-install --with-system-dependencies
```

## Basic Usage

### Sources

You may parse a document from a path or from raw bytes:

```php
use Shipfastlabs\Parsel;

$markdown = Parsel::file('/path/to/report.pdf')->markdown();

$markdown = Parsel::bytes($contents, 'pdf')->markdown();
```

> [!NOTE]
> Byte sources always require a file extension so the parser can identify the format.

### Output

Every driver can return Markdown. LiteParse can also return plain text, a structured `Document`, or an array:

```php
$markdown = Parsel::file('report.pdf')->markdown();
$text = Parsel::file('report.pdf')->text();
$array = Parsel::file('report.pdf')->toArray();

$document = Parsel::file('report.pdf')->parse();

echo $document->pageCount();

foreach ($document->pages as $page) {
    foreach ($page->items as $item) {
        echo "{$item->text} @ ({$item->x}, {$item->y})";
    }
}
```

For large documents, the `lazyPages` method streams pages one at a time:

```php
foreach (Parsel::file('large.pdf')->lazyPages() as $page) {
    echo $page->text;
}
```

The `screenshots` method renders each page to a PNG in an existing directory and returns the file paths:

```php
$paths = Parsel::file('report.pdf')->screenshots('/path/to/screenshots');
```

The `save` method writes the output to disk, choosing the format from the extension: `.md` and `.markdown` save Markdown, `.json` saves structured JSON, and any other extension saves plain text:

```php
Parsel::file('report.pdf')->save('report.md');
Parsel::file('report.pdf')->save('report.json');
```

### Timeouts

Parsing times out after 60 seconds by default. You may change the timeout for a single parse, or for every parse, in seconds. Passing `0` or `null` disables it:

```php
Parsel::file('report.pdf')->withTimeout(120)->markdown();

Parsel::defaultTimeout(300);
```

## Drivers

### Selecting a Driver

LiteParse is the default driver. You may select another driver per parse, or change the default:

```php
$markdown = Parsel::driver('anydoc')->file('report.docx')->markdown();

Parsel::defaultDriver('anydoc');
```

If you prefer dependency injection over the static facade, you may use a `ParselManager` instance:

```php
use Shipfastlabs\Parsel\ParselManager;

$parsel = new ParselManager;

$markdown = $parsel->driver('anydoc')->file('report.docx')->markdown();
```

### Capabilities

| Capability | LiteParse | AnyDoc |
| --- | --- | --- |
| Markdown | Yes | Yes |
| Plain text | Yes | No |
| Structured documents and JSON | Yes | No |
| Lazy pages | Yes | No |
| Screenshots | Yes | No |
| Local OCR | Yes | No |

Calling an unsupported method throws an `UnsupportedCapabilityException` before the parser runs.

### Supported Formats

| Input | LiteParse | AnyDoc |
| --- | --- | --- |
| PDF | Yes | Yes |
| Word, Excel, PowerPoint | Via LibreOffice | Yes |
| OpenDocument, RTF, CSV | Via LibreOffice | Yes |
| EPUB | No | Yes |
| Images | Via OCR | No |

### Binary Resolution

Each driver locates its executable in the following order:

1. The `binary` provider option, set via `withBinary()`.
2. The `PARSEL_LITEPARSE_BINARY` or `PARSEL_ANYDOC_BINARY` environment variable.
3. `lit` or `anydoc` on your `PATH`.

## Provider Options

Driver-specific settings are passed to `withProviderOptions` using a typed options object or an array.

### LiteParse Options

```php
use Shipfastlabs\Parsel\Enums\ImageMode;
use Shipfastlabs\Parsel\Options\LiteParseOptions;

$markdown = Parsel::file('invoice.pdf')
    ->withProviderOptions(
        LiteParseOptions::make()
            ->pageRange(1, 5)
            ->page(10)
            ->maxPages(20)
            ->withDpi(300)
            ->withPassword($password)
            ->withImages(ImageMode::Embed, '/path/to/images')
            ->withoutLinks()
            ->keepHeadersAndFooters()
    )
    ->markdown();
```

#### OCR

You may enable OCR with `withOcr()`, optionally choosing a language, worker count, local Tesseract data directory, or an HTTP OCR server:

```php
LiteParseOptions::make()->withOcr(language: 'eng', workers: 4);

LiteParseOptions::make()->withOcr(tessdataPath: '/usr/share/tessdata');

LiteParseOptions::make()
    ->withOcr(serverUrl: 'https://ocr.example.com', headers: ['Authorization' => "Bearer {$token}"])
    ->withOcrServerHeader('X-Tenant', 'acme');
```

> [!NOTE]
> OCR is disabled unless you enable it, so scanned PDFs and images return empty text by default.

#### Structured Output

You may ask LiteParse to enrich structured output with `extractBlocks`, `extractAnnotations`, `extractFormFields`, `extractStructureTree`, `extractContentBounds`, `extractVectorGraphics`, `extractTextMetadata`, `extractImages`, `extractXfaPackets`, and `withComplexity`, or enable all of them with `extractAll`. These options apply to `parse`, `toArray`, `lazyPages`, and `.json` saves:

```php
LiteParseOptions::make()->extractBlocks()->extractFormFields();
```

#### Other Options

You may skip damaged pages, load a LiteParse config file, or pass any CLI flag that Parsel does not cover yet. The `option` method applies to parsing, while `screenshotOption` applies to screenshots:

```php
LiteParseOptions::make()
    ->continueOnPageError()
    ->withConfig('/path/to/liteparse.json')
    ->option('new-flag', 42)
    ->screenshotOption('new-screenshot-flag');
```

### AnyDoc Options

You may set the input format explicitly or pass any CLI flag via `option`:

```php
use Shipfastlabs\Parsel\Options\AnyDocOptions;

$markdown = Parsel::driver('anydoc')
    ->file('data.csv')
    ->withProviderOptions(AnyDocOptions::make()->format('csv'))
    ->markdown();
```

AnyDoc does not run OCR locally. You may send scanned PDFs to [Firecrawl](https://www.firecrawl.dev) for hosted OCR instead. When no API key or URL is given, AnyDoc reads `FIRECRAWL_API_KEY` and `FIRECRAWL_API_URL`:

```php
$markdown = Parsel::driver('anydoc')
    ->file('scan.pdf')
    ->withProviderOptions(AnyDocOptions::make()->withHostedOcr())
    ->markdown();
```

> [!WARNING]
> Hosted OCR uploads the entire document to Firecrawl or the server at `api_url`. Only enable it for documents you are allowed to share with that service.

### Array Options

Every option has a snake_case array key. Keys are validated, so typos throw an `InvalidProviderOptionsException`:

```php
Parsel::file('receipt.png')
    ->withProviderOptions(['ocr' => true, 'ocr_language' => 'eng'])
    ->text();
```

## Handling Errors

Parser, filesystem, and option failures extend `Shipfastlabs\Parsel\Exceptions\ParselException`. Invalid arguments, such as an empty path, an unsafe byte extension, or an unknown image mode, throw a standard `InvalidArgumentException` or `ValueError`. When a parser exits with an error, Parsel throws a `ParseFailedException` exposing `exitCode`, `stderr`, and `command`. When it exceeds the timeout, Parsel throws a `ParseTimedOutException` exposing `timeout`, `driver`, and `command`:

```php
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\ParseTimedOutException;

try {
    $markdown = Parsel::file('report.pdf')->markdown();
} catch (ParseTimedOutException $e) {
    report("{$e->driver} timed out after {$e->timeout}s");
} catch (ParseFailedException $e) {
    report($e->stderr);
}
```

The AnyDoc driver throws more specific subclasses of `ParseFailedException`: a `ParserUsageException` for invalid arguments, and an `OcrRequiredException` when a PDF needs OCR:

```php
use Shipfastlabs\Parsel\Exceptions\OcrRequiredException;
use Shipfastlabs\Parsel\Options\LiteParseOptions;

try {
    $markdown = Parsel::driver('anydoc')->file('scan.pdf')->markdown();
} catch (OcrRequiredException) {
    $markdown = Parsel::file('scan.pdf')
        ->withProviderOptions(LiteParseOptions::make()->withOcr())
        ->markdown();
}
```

Secrets such as passwords, API keys, and OCR server headers are redacted from the exception's `command`, so it is safe to log.

## Custom Drivers

You may register your own driver with the `extend` method. A driver implements the `Driver` contract, which provides Markdown, and may implement `TextDriver`, `StructuredDocumentDriver`, `LazyPageDriver`, or `ScreenshotDriver` for additional capabilities:

```php
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\ParselManager;

Parsel::extend('company-api', fn (ParselManager $manager): Driver => new CompanyApiDriver);

$markdown = Parsel::driver('company-api')->file('report.pdf')->markdown();
```

The factory receives the `ParselManager`. Its `processRunner` and `filesystem` methods return the process runner and filesystem the bundled drivers use, so a driver built on them respects `Parsel::fake()`.

When `withProviderOptions` is called more than once, Parsel merges string `pages` selections and the `extra` and `screenshot_extra` arrays. Every other key is replaced by the latest value.

## Testing

The `fake` method replaces the parser processes with canned responses, matched against a substring of the command:

```php
$fake = Parsel::fake([
    '--format json' => file_get_contents(__DIR__.'/fixtures/lit-output.json'),
    'anydoc' => '# Converted document',
]);

$document = Parsel::bytes($pdf, 'pdf')->parse();
$markdown = Parsel::driver('anydoc')->bytes($docx, 'docx')->markdown();

expect($fake->ranCount())->toBe(2);
```

Only the parser processes are faked, so a path passed to `file` must still exist. Use `bytes` when the document isn't on disk.

The fake, default driver, default timeout, and registered drivers are kept for the whole process. Call `Parsel::flush()` in your test teardown to reset them:

```php
afterEach(fn () => Parsel::flush());
```

You may return a `ProcessResult` to simulate a failure or timeout:

```php
use Shipfastlabs\Parsel\Support\ProcessResult;

Parsel::fake(['anydoc' => new ProcessResult(143, '', '', ['anydoc'], timedOutAfter: 30.0)]);
```

## Upgrading

Please see [UPGRADE.md](UPGRADE.md) for upgrade instructions.

## Contributing

You may run the test suite with Composer:

```bash
composer test
```

The integration tests run against the real `lit` and `anydoc` binaries. Set `PARSEL_REQUIRE_BINARIES=1` to fail instead of skip when a binary is missing, and `PARSEL_INTEGRATION_EXTENDED=1` to include tests that need network access or LibreOffice:

```bash
vendor/bin/pest --group=integration
```

## Credits and License

Parsel is maintained by [Shipfastlabs](https://shipfastlabs.com) and released under the [MIT license](LICENSE.md).
