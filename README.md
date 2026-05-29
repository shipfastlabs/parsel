# Parsel

<p align="center">
    <a href="https://github.com/shipfastlabs/parsel/actions"><img alt="Tests" src="https://github.com/shipfastlabs/parsel/actions/workflows/tests.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="Latest Version" src="https://img.shields.io/packagist/v/shipfastlabs/parsel"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/shipfastlabs/parsel"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="License" src="https://img.shields.io/packagist/l/shipfastlabs/parsel"></a>
</p>

------

A pure-PHP, expressive wrapper around [**liteparse**](https://github.com/run-llama/liteparse) — run-llama's
fast, fully-local document parser turning PDFs, Office documents and images into text or richly-structured data with bounding boxes.

```php
use Shipfastlabs\Parsel;

// Plain text
$text = Parsel::file('invoice.pdf')->text();

// Structured, with per-item coordinates
$document = Parsel::file('invoice.pdf')
    ->pageRange(1, 5)
    ->withOcr(language: 'eng')
    ->withDpi(300)
    ->parse();

foreach ($document->pages as $page) {
    foreach ($page->items as $item) {
        echo "{$item->text} @ ({$item->x}, {$item->y})\n";
    }
}
```

> **Requires [PHP 8.4+](https://php.net/releases/)** and the `lit` binary (see below).

## Installation

Install the package via Composer:

```bash
composer require shipfastlabs/parsel
```

Parsel is *bring-your-own-binary* — install liteparse's `lit` CLI with whichever toolchain you prefer:

```bash
cargo install liteparse          # Rust
pip install liteparse            # Python
npm i -g @llamaindex/liteparse   # Node
```

Parsing Office documents or images additionally needs **LibreOffice** and **ImageMagick** on the host;
OCR uses bundled **Tesseract**. liteparse runs entirely on your machine — no cloud, no API keys.

## Usage

### Sources

```php
Parsel::file('/path/to/report.pdf');          // a file on disk
Parsel::bytes($uploadedBytes, 'pdf');          // raw bytes (e.g. an upload) — written to a temp file
```

### Page selection

`page()`, `pages()` and `pageRange()` are additive and normalise to liteparse's `--target-pages`:

```php
Parsel::file('doc.pdf')->page(7);                 // a single page
Parsel::file('doc.pdf')->pages(1, 3, 5);          // specific pages
Parsel::file('doc.pdf')->pages('1-5', 10);        // ranges and pages
Parsel::file('doc.pdf')->pageRange(1, 5);         // an inclusive range → "1-5"
Parsel::file('doc.pdf')->pageRange(1, 5)->page(10); // additive → "1-5,10"
Parsel::file('doc.pdf')->maxPages(50);            // cap the number of pages parsed
```

### OCR

OCR is **disabled by default** for speed and predictability. Enable it with `withOcr()`, passing any OCR
settings as named arguments. Use `withoutOcr()` to be explicit about disabling it.

```php
// Enable OCR with default settings
Parsel::file('scan.pdf')->withOcr()->text();

// Enable OCR and configure it
Parsel::file('scan.pdf')->withOcr(
    language: 'fra',                          // Tesseract language code  (--ocr-language)
    tessdataPath: '/usr/share/tessdata',      // --tessdata-path
    serverUrl: 'http://localhost:8828/ocr',   // external OCR server      (--ocr-server-url)
    workers: 8,                               // concurrent OCR workers   (--num-workers)
)->text();

// Explicitly disable OCR (the default)
Parsel::file('doc.pdf')->withoutOcr()->text();
```

### Rendering & misc

```php
Parsel::file('doc.pdf')->withDpi(300);                 // rendering DPI
Parsel::file('doc.pdf')->preserveSmallText();          // keep very small text
Parsel::file('secret.pdf')->withPassword('hunter2');   // encrypted documents
Parsel::file('doc.pdf')->withBinary('/usr/local/bin/lit'); // per-call binary override
Parsel::file('doc.pdf')->withTimeout(120);             // process timeout in seconds
```

### Escape hatch

Any `lit` flag not yet modelled by a method can be passed through with `option()`:

```php
Parsel::file('doc.pdf')->option('some-new-flag');       // → --some-new-flag
Parsel::file('doc.pdf')->option('some-new-flag', 42);   // → --some-new-flag 42
```

### Terminals

```php
$text  = Parsel::file('doc.pdf')->text();           // string
$doc   = Parsel::file('doc.pdf')->parse();          // Document
$array = Parsel::file('doc.pdf')->toArray();         // array
$path  = Parsel::file('doc.pdf')->save('out.json');  // ".json" → JSON, anything else → text
$pngs  = Parsel::file('doc.pdf')->screenshots('/tmp/pages'); // PNG paths in the output directory
```

> `screenshots()` returns the image files found in the output directory after `lit` runs — point it at a
> dedicated, empty directory.

### Streaming large documents

`parse()` loads the whole document into memory. For very large PDFs, `lazyPages()` streams the result one
`Page` at a time at roughly constant memory — `lit` writes its JSON to a temp file and Parsel parses it
incrementally (via [`halaxa/json-machine`](https://github.com/halaxa/json-machine)):

```php
foreach (Parsel::file('huge-10k.pdf')->lazyPages() as $page) {
    foreach ($page->items as $item) {
        // process one page at a time — the full document is never held in memory
    }
}
```

### The `Document`

```php
$document->text;          // string — full document text
$document->pageCount();   // int
$document->pages;         // list<Page>

$page->number;            // int
$page->width;             // float
$page->height;            // float
$page->text;              // string
$page->items;             // list<TextItem>

$item->text;              // string
$item->x; $item->y;       // float — top-left position (PDF points)
$item->width; $item->height;
$item->fontName;          // ?string
$item->fontSize;          // ?float
$item->confidence;        // ?float — OCR confidence (0–1)
```

## Binary resolution

When you call a terminal, Parsel resolves the `lit` binary in this order:

1. A per-call override — `->withBinary('/path/to/lit')`
2. A global default — `Parsel::usingBinary('/path/to/lit')`
3. The `PARSEL_LIT_BINARY` environment variable
4. `lit` on the system `PATH`

If none resolve, a `BinaryNotFoundException` is thrown.

```php
Parsel::usingBinary('/usr/local/bin/lit'); // set once during bootstrap
Parsel::defaultTimeout(120);
```

## Testing

Parsel ships a fake runner so your test suite never spawns the real binary:

```php
use Shipfastlabs\Parsel;

$fake = Parsel::fake([
    '--format json' => file_get_contents(__DIR__.'/fixtures/lit-output.json'),
]);

$document = Parsel::file('invoice.pdf')->parse();

expect($fake->recordedCommands()[0])->toContain('--format', 'json');
```

Each response key is matched against the command line as a substring; the **longest** matching key wins. A
string value becomes successful stdout, or pass a `ProcessResult` for full control over exit code and stderr.

## Architecture

Parsel talks to `lit` synchronously via [Symfony Process](https://symfony.com/doc/current/components/process.html),
behind a small `ProcessRunner` seam. That seam means a different execution strategy (a long-running server, or
an async/concurrent runner) can be slotted in later without changing the public API.

## Development

```bash
composer lint          # Pint + Rector (fix)
composer test:types    # PHPStan (level max)
composer test:unit     # Pest with 100% coverage
composer test          # the whole suite
```

The `tests/Integration` group runs against a real `lit` install and is skipped when the binary is absent:

```bash
./vendor/bin/pest --group=integration
```

## Credits

Parsel wraps [liteparse](https://github.com/run-llama/liteparse) by run-llama. Built by
**[Shipfastlabs](https://shipfastlabs.com)** under the [MIT license](LICENSE.md).
