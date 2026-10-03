# Parsel

<p align="center">
    <img src="./art/og.png" height="300" alt="Parsel">
</p>

<p align="center">
    <a href="https://github.com/shipfastlabs/parsel/actions"><img alt="Tests" src="https://github.com/shipfastlabs/parsel/actions/workflows/tests.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="Latest Version" src="https://img.shields.io/packagist/v/shipfastlabs/parsel"></a>
    <a href="https://packagist.org/packages/shipfastlabs/parsel"><img alt="License" src="https://img.shields.io/packagist/l/shipfastlabs/parsel"></a>
</p>

Parsel provides one expressive PHP API for document parsing, backed by interchangeable drivers. Version 1.0 includes local [LiteParse](https://github.com/run-llama/liteparse) and [AnyDoc](https://github.com/firecrawl/anydoc) drivers and public contracts for custom or future hosted providers.

```php
use Shipfastlabs\Parsel;

$markdown = Parsel::file('report.pdf')->markdown(); // LiteParse by default

$markdown = Parsel::driver('anydoc')
    ->file('report.docx')
    ->markdown();
```

Parsel requires PHP 8.4 or greater.

## Installation

```bash
composer require shipfastlabs/parsel
```

Install only the parser you use. With no `--driver`, the installer installs LiteParse:

```bash
vendor/bin/parsel-install
vendor/bin/parsel-install --driver=anydoc
vendor/bin/parsel-install --driver=all
```

LiteParse supports npm, pnpm, bun, pip, and cargo. AnyDoc supports npm, pnpm, and bun and requires Node.js 20 or newer.

```bash
vendor/bin/parsel-install --driver=liteparse --manager=cargo
vendor/bin/parsel-install --driver=anydoc --manager=npm
vendor/bin/parsel-install --driver=liteparse --with-system-dependencies
```

`vendor/bin/parsel-install-lit` remains as a compatibility alias.

## Drivers and capabilities

| Capability | LiteParse | AnyDoc |
| --- | --- | --- |
| Markdown | Yes | Yes |
| Plain text | Yes | No |
| Structured pages and coordinates | Yes | No |
| Lazy pages | Yes | No |
| Screenshots | Yes | No |
| Complexity / OCR detection | Yes | No |
| OCR | Yes | No |

Parsel is tested against LiteParse 2.15.x and AnyDoc 0.2.x.

### Supported formats

| Input | LiteParse | AnyDoc |
| --- | --- | --- |
| PDF | Yes, natively | Yes (text layer only, see below) |
| Word, PowerPoint, Excel (`doc`, `docx`, `ppt`, `pptx`, `xls`, `xlsx`, ...) | Yes, via LibreOffice conversion | Yes, natively |
| OpenDocument (`odt`, `ods`, `odp`) | Yes, via LibreOffice conversion | Yes, natively |
| RTF, CSV | Yes, via LibreOffice conversion | Yes, natively |
| EPUB | No | Yes |
| Images (`png`, `jpg`, `tiff`, `webp`, ...) | Yes, via OCR | No |

AnyDoc accepts `doc`, `docx`, `odt`, `pdf`, `ppt`, `pptx`, `rtf`, `epub`, `xlsx`, `ods`, `odp`, and `csv`, plus extension aliases such as `xls`, `docm`, and `ppsx`. It rejects images (`anydoc sample.png` exits with "unsupported input") and does no local OCR: a scanned or image-only PDF fails with exit code 3, surfaced as an `OcrRequiredException` (see [Handling failures](#handling-failures)).

LiteParse needs LibreOffice for Office, OpenDocument, RTF, and CSV input. Install it with `vendor/bin/parsel-install --with-system-dependencies` (which also installs ImageMagick) or through your system package manager.

Calling an unavailable operation throws `UnsupportedCapabilityException` before the provider is executed. Parsel does not derive fake structured data or plain text from AnyDoc Markdown.

LiteParse remains the default driver, so existing basic calls continue to work:

```php
$text = Parsel::file('invoice.pdf')->text();
$document = Parsel::file('invoice.pdf')->parse();
$array = Parsel::file('invoice.pdf')->toArray();
```

Select AnyDoc explicitly or change the process-wide default:

```php
Parsel::driver('anydoc')->file('book.epub')->markdown();

Parsel::defaultDriver('anydoc');
Parsel::file('book.epub')->markdown();
```

For dependency injection and long-running applications, use an instance:

```php
use Shipfastlabs\Parsel\ParselManager;

$parsel = new ParselManager;
$markdown = $parsel->driver('anydoc')->file('report.docx')->markdown();
```

## Sources and common options

Both drivers accept paths and raw bytes. Byte sources require an extension so signature-less formats such as CSV can be identified reliably.

```php
$markdown = Parsel::file('/path/to/report.pdf')->markdown();
$markdown = Parsel::bytes($uploadedBytes, 'pdf')->markdown();

$markdown = Parsel::driver('anydoc')
    ->bytes($csvBytes, 'csv')
    ->markdown();
```

AnyDoc streams byte sources to `anydoc -` over stdin instead of writing a temporary file when the extension is one anydoc recognizes (`pdf`, `doc`, `docx`, `docm`, `odt`, `rtf`, `epub`, `ppt`, `pps`, `pot`, `pptx`, `pptm`, `ppsx`, `ppsm`, `odp`, `xls`, `xlsx`, `xlsm`, `xlsb`, `ods`, `csv`) or when you set an explicit format. anydoc detects the format from the content, as it does for files. Signature-less CSV gets `--format csv` automatically. Bytes with any other extension and no explicit format still go through a temporary file. LiteParse always uses a temporary file for byte sources.

Timeout is portable across drivers:

```php
Parsel::file('report.pdf')->withTimeout(120)->markdown();
```

When the parser exceeds the timeout, its process is stopped, any temporary file created for a byte source is removed, and a `ParseTimedOutException` is thrown. Like every Parsel error it extends `ParselException`, and it exposes the exceeded `timeout` (seconds), the `driver` name, and the `command` that was run. A parser that exits with a non-zero code throws `ParseFailedException` instead.

```php
use Shipfastlabs\Parsel\Exceptions\ParseTimedOutException;

try {
    $markdown = Parsel::file('report.pdf')->withTimeout(30)->markdown();
} catch (ParseTimedOutException $e) {
    report("{$e->driver} gave up after {$e->timeout}s");
}
```

`save()` selects the corresponding capability from the extension. Both drivers support `.md` and `.markdown`; LiteParse additionally supports `.txt` and `.json`.

```php
Parsel::driver('anydoc')->file('report.docx')->save('report.md');
Parsel::file('report.pdf')->save('report.json');
```

## Provider options

Provider-specific behavior belongs in `withProviderOptions()`. It accepts a typed fluent object or a strict associative array.

```php
use Shipfastlabs\Parsel\Options\LiteParseOptions;

$options = LiteParseOptions::make()
    ->pageRange(1, 5)
    ->page(10)
    ->withOcr(language: 'eng', workers: 8)
    ->withDpi(300)
    ->preserveSmallText();

$document = Parsel::file('invoice.pdf')
    ->withProviderOptions($options)
    ->parse();
```

### OCR is off unless you enable it

The `lit` CLI runs OCR by default, but Parsel always passes `--no-ocr` unless you opt in with `ocr()`, `withOcr()`, or the `'ocr' => true` array option. Scanned PDFs and images therefore return empty text until OCR is enabled:

```php
$text = Parsel::file('scanned.pdf')->text(); // empty: no text layer and OCR is disabled

$text = Parsel::file('scanned.pdf')
    ->withProviderOptions(LiteParseOptions::make()->withOcr())
    ->text();

$text = Parsel::file('receipt.png')
    ->withProviderOptions(['ocr' => true, 'ocr_language' => 'eng'])
    ->text();
```

LiteParse's built-in Tesseract OCR downloads language data from GitHub on first use, so the first OCR run needs network access. Use `withOcr(serverUrl: ...)` to send pages to an HTTP OCR server instead.

LiteParse options include page selection, maximum pages, OCR settings (including OCR server headers), page-error recovery, config files, DPI, small-text preservation, passwords, Markdown images and links, headers and footers, JSON enrichments, and a binary override.

To OCR with local Tesseract language data instead of letting LiteParse download it, point `tessdataPath` at a directory containing `<language>.traineddata` files. LiteParse no longer has a `--tessdata-path` flag, so Parsel passes this through a temporary `--config` file that is removed after the parse. LiteParse reads only one config file, so when you also use `withConfig()`, Parsel copies your settings into that temporary file together with `tessdataPath`.

```php
LiteParseOptions::make()->withOcr(language: 'eng', tessdataPath: '/usr/share/tessdata');
```

```php
use Shipfastlabs\Parsel\Enums\ImageMode;

$markdown = Parsel::file('report.pdf')
    ->withProviderOptions(
        LiteParseOptions::make()
            ->withoutOcr()
            ->withImages(ImageMode::Embed, '/path/to/images')
            ->withoutLinks()
            ->keepHeadersAndFooters()
    )
    ->markdown();
```

Remote OCR servers can receive extra request headers (sent only when OCR is enabled), damaged pages can be skipped instead of failing the whole parse, and a LiteParse JSON config file can be loaded. Options set through Parsel are passed as CLI flags, so they take precedence over the config file.

```php
$document = Parsel::file('scan.pdf')
    ->withProviderOptions(
        LiteParseOptions::make()
            ->withOcr(serverUrl: 'https://ocr.example.com', headers: ['Authorization' => 'Bearer '.$token])
            ->withOcrServerHeader('X-Tenant', 'acme')
            ->continueOnPageError()
            ->withConfig('/path/to/liteparse.json')
    )
    ->parse();

// Equivalent strict array keys
$options = [
    'ocr' => true,
    'ocr_server_url' => 'https://ocr.example.com',
    'ocr_server_headers' => ['Authorization' => 'Bearer '.$token],
    'continue_on_page_error' => true,
    'config' => '/path/to/liteparse.json',
];
```

AnyDoc supports explicit input format and binary overrides:

```php
use Shipfastlabs\Parsel\Options\AnyDocOptions;

$markdown = Parsel::driver('anydoc')
    ->file('data.csv')
    ->withProviderOptions(
        AnyDocOptions::make()->format('csv')
    )
    ->markdown();
```

AnyDoc does not run OCR itself. By default (`--ocr reject`) a PDF whose pages are scanned or image-only fails with a `ParseFailedException`. AnyDoc 0.2.4+ can instead send those PDFs to [Firecrawl Parse](https://www.firecrawl.dev):

```php
use Shipfastlabs\Parsel\Enums\AnyDocOcrMode;

$markdown = Parsel::driver('anydoc')
    ->file('scan.pdf')
    ->withProviderOptions(
        AnyDocOptions::make()->withHostedOcr() // or ->withHostedOcr($apiKey, 'https://firecrawl.example.com')
    )
    ->markdown();

AnyDocOptions::make()->ocr(AnyDocOcrMode::Hosted); // or ->ocr('hosted')
AnyDocOptions::make()->rejectOcr();                // explicit default
```

The equivalent array keys are `ocr` (`reject` or `hosted`), `api_key`, and `api_url`. Without an explicit key AnyDoc reads `FIRECRAWL_API_KEY` (else runs keyless), and without a URL it reads `FIRECRAWL_API_URL` (else `https://api.firecrawl.dev`). Prefer the environment variable for the key: an explicit `api_key` is passed as a command-line argument, which other users on the same machine may be able to see in the process list.

> **Privacy:** hosted OCR uploads the whole document to Firecrawl (or the server at `api_url`) for processing. Only enable it for documents you are allowed to share with that service. Documents that do not need OCR are still converted locally.

Array keys are validated, so typos fail early. For a newly released upstream CLI flag, use the explicit escape hatch:

```php
$options = LiteParseOptions::make()->option('new-upstream-flag', 42);
$options = AnyDocOptions::make()->option('new-upstream-flag');
```

LiteParse `option()` flags are passed to `lit parse` only (used by `markdown()`, `text()`, `parse()`, `toArray()`, `save()`, and `lazyPages()`), because most parse flags are rejected by `lit screenshot`. Use `screenshotOption()` for a flag that should be passed to `lit screenshot` instead:

```php
$options = LiteParseOptions::make()
    ->option('extract-blocks')                     // lit parse only
    ->screenshotOption('new-screenshot-flag', 2);  // lit screenshot only
```

As an array, these are the `extra` and `screenshot_extra` keys.

When the CLI exits with a non-zero code, Parsel throws `ParseFailedException` with the `exitCode`, `stderr`, and the `command` that was run. Values of secret flags (`--password`, `--api-key`, `--ocr-server-header`, including the `--flag=value` form) are replaced with `********` in `command`, so the exception is safe to log or report.

## Handling failures

Every provider process that exits with a non-zero code throws `ParseFailedException`, which exposes `exitCode`, `stderr`, and `command`. The AnyDoc driver maps its documented exit codes to more specific subclasses, so existing `catch (ParseFailedException)` blocks keep working:

| AnyDoc exit code | Exception |
| --- | --- |
| 1, document could not be read or converted | `ParseFailedException` |
| 2, usage error such as an unknown option or invalid `--format` | `ParserUsageException` |
| 3, PDF pages need OCR | `OcrRequiredException` |

```php
use Shipfastlabs\Parsel\Exceptions\OcrRequiredException;
use Shipfastlabs\Parsel\Options\LiteParseOptions;

try {
    $markdown = Parsel::driver('anydoc')->file('scan.pdf')->markdown();
} catch (OcrRequiredException) {
    $markdown = Parsel::driver('liteparse')
        ->file('scan.pdf')
        ->withProviderOptions(LiteParseOptions::make()->withOcr())
        ->markdown();
}
```

Alternatively, `AnyDocOptions::make()->withHostedOcr()` makes AnyDoc send the document to Firecrawl Parse for hosted OCR. LiteParse failures always throw `ParseFailedException`.

## Structured LiteParse output

```php
$document = Parsel::file('document.pdf')->parse();

echo $document->text;
echo $document->pageCount();

foreach ($document->pages as $page) {
    foreach ($page->items as $item) {
        echo "{$item->text} @ ({$item->x}, {$item->y})\n";
    }
}
```

LiteParse emits richer per-page data when you pass the matching CLI flags (for now through `option()`). A field stays `null` when its flag was not passed:

| Flag | Field |
| --- | --- |
| `extract-text-metadata` | `$item->rotation` (font metrics and colors such as `fontWeight`, `fontHeight`, `fontAscent`, `fontDescent`, `textWidth`, `fillColor`, `strokeColor` are filled whenever LiteParse reports them) |
| `extract-content-bounds` | `$page->contentBounds` (`BoundingBox` with `x`, `y`, `width`, `height`) |
| `complexity` | `$page->complexity` |
| `extract-annotations` | `$page->annotations` |
| `extract-form-fields` | `$page->formFields`, `$document->formType()` |
| `extract-structure-tree` | `$page->structureTree` |
| `extract-vector-graphics` | `$page->vectorGraphics` |
| `extract-images` | `$document->images()` |

```php
$document = Parsel::file('document.pdf')
    ->withProviderOptions(LiteParseOptions::make()->option('extract-content-bounds')->option('complexity'))
    ->parse();

$page = $document->page(1);
$page->contentBounds?->width;
$page->complexity['needs_ocr'] ?? null;
```

Complexity, annotations, form fields, the structure tree, vector graphics and images are exposed as the raw arrays decoded from LiteParse's JSON. The top-level `images` and `form_type` keys also remain in `$document->metadata`.

Stream large documents without decoding the complete page array:

```php
foreach (Parsel::file('large.pdf')->lazyPages() as $page) {
    echo $page->text;
}
```

LiteParse can enrich its JSON output with extra data. These options only apply to structured output (`parse()`, `toArray()`, `lazyPages()`, and `save('*.json')`); they are not sent for `text()` or `markdown()`:

```php
$document = Parsel::file('form.pdf')
    ->withProviderOptions(
        LiteParseOptions::make()
            ->extractBlocks()          // classified layout blocks with bounding boxes
            ->extractAnnotations()     // all PDF annotations
            ->extractFormFields()      // AcroForm widget fields and values
            ->extractStructureTree()   // tagged-PDF logical structure tree
            ->extractContentBounds()   // per-page content bounds
            ->extractVectorGraphics()  // vector shapes and merged lines
            ->extractTextMetadata()    // rich text metadata on text items
            ->extractImages()          // embedded image bytes and metadata
            ->extractXfaPackets()      // raw XFA packets
            ->withComplexity()         // per-page complexity signals
    )
    ->parse();

// Or enable everything at once:
LiteParseOptions::make()->extractAll();
```

The equivalent array keys are `extract_blocks`, `extract_annotations`, `extract_form_fields`, `extract_structure_tree`, `extract_content_bounds`, `extract_vector_graphics`, `extract_text_metadata`, `extract_images`, `extract_xfa_packets`, and `complexity`. Document-level results such as `images`, `form_type`, and `xfa_packets` appear in `$document->metadata`; the complete enriched payload is available with `save('output.json')`.

Screenshots require an existing destination directory:

```php
$files = Parsel::file('document.pdf')
    ->withProviderOptions(LiteParseOptions::make()->pageRange(1, 5)->withDpi(200))
    ->screenshots('/path/to/screenshots');
```

LiteParse writes one `page_<N>.png` file per rendered page. `screenshots()` renders into a private temporary directory, moves the produced files into the destination (replacing same-named files, as LiteParse itself does) and returns exactly those paths, sorted by page number. Other files already in the directory are left alone and not returned.

## Complexity and OCR detection

LiteParse can check whether a document needs OCR before you run an expensive parse. `complexity()` runs `lit is-complex` and returns per-page signals; `needsOcr()` is a shortcut for the overall verdict. Page selection, maximum pages, password, and binary options are reused; other parse options are ignored.

```php
if (Parsel::file('scan.pdf')->needsOcr()) {
    $document = Parsel::file('scan.pdf')
        ->withProviderOptions(LiteParseOptions::make()->withOcr())
        ->parse();
}

$complexity = Parsel::file('report.pdf')
    ->withProviderOptions(LiteParseOptions::make()->pageRange(1, 10))
    ->complexity();

$complexity->needsOcr();               // bool
$complexity->pagesNeedingOcr();        // [1, 3]
$complexity->pagesWithComplexLayout(); // pages with tables, columns, or dense graphics

$page = $complexity->page(1);
$page->reasons;          // ['sparse-text', 'embedded-images']
$page->layout?->reasons; // ['table-likely']
```

Layout signals (`hasComplexLayout()`) are independent of the OCR verdict: they indicate that the text-only path may mangle reading order or structure.

## Binary resolution

Each local driver resolves its executable in this order:

1. The typed or array provider option `binary`.
2. `PARSEL_LITEPARSE_BINARY` or `PARSEL_ANYDOC_BINARY`.
3. `lit` or `anydoc` on `PATH`.

`PARSEL_LIT_BINARY` remains a fallback for LiteParse during the 1.0 migration.

## Custom drivers

Implement the minimal `Driver` contract for Markdown, then opt into additional capability contracts only when the provider supports them.

```php
use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\ParseRequest;
use Shipfastlabs\Parsel\ParselManager;

Parsel::extend('company-api', function (ParselManager $manager): Driver {
    return new CompanyApiDriver;
});

$markdown = Parsel::driver('company-api')->file('report.pdf')->markdown();
```

Drivers are resolved lazily and cached by the manager. Implement `TextDriver`, `StructuredDocumentDriver`, `LazyPageDriver`, `ScreenshotDriver`, or `ComplexityDriver` to add those operations. A remote driver may use any HTTP client and does not need to depend on Parsel's CLI process infrastructure.

## Testing

`Parsel::fake()` swaps the shared local process runner and matches canned responses against command substrings:

```php
$fake = Parsel::fake([
    '--format json' => file_get_contents(__DIR__.'/fixtures/lit-output.json'),
    'anydoc' => '# Converted document',
]);

$document = Parsel::file('invoice.pdf')->parse();
$markdown = Parsel::driver('anydoc')->file('report.docx')->markdown();

expect($fake->ranCount())->toBe(2);
```

Return a `ProcessResult` with `timedOutAfter` set to simulate a timeout:

```php
use Shipfastlabs\Parsel\Support\ProcessResult;

Parsel::fake(['anydoc' => new ProcessResult(143, '', '', ['anydoc'], timedOutAfter: 30.0)]);
```

See [UPGRADE.md](UPGRADE.md) when moving from Parsel 0.x.

## Development

```bash
composer test
vendor/bin/pest --group=integration
```

The integration group runs Parsel against the real `lit` and `anydoc` binaries (resolved from `PARSEL_LITEPARSE_BINARY`, `PARSEL_ANYDOC_BINARY` or your `PATH`) and exercises every CLI flag Parsel emits. Tests skip when a binary is missing; set `PARSEL_REQUIRE_BINARIES=1` to make them fail instead, as the Integration workflow does on every push and weekly against the latest upstream releases. Tests that need network access for OCR models or a working LibreOffice install only run with `PARSEL_INTEGRATION_EXTENDED=1`.

## Credits

Parsel is maintained by [Shipfastlabs](https://shipfastlabs.com) and released under the [MIT license](LICENSE.md).
