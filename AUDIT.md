# Code Audit

Tick an item when it lands, and note the commit next to it.

- P0: broken today, the behaviour is wrong.
- P1: misleading today, users or contributors will get it wrong.
- P2: inconsistent with siblings or other layers, or costly to change.
- P3: polish, duplication, dead code.

Every fix below keeps the public API intact: no class, method, constant, parameter or caught exception type is removed or renamed.

## P0: Broken

- [x] 1. `ParseTimedOutException` exposes secrets in `$command`. `ParseTimedOutException::fromResult()` (`src/Exceptions/ParseTimedOutException.php:36-39`) passes the raw `$result->command` to `after()`, which stores it unchanged. The timeout is thrown from `src/Support/CliProcess.php:55`. Every timed-out parse therefore leaks its secrets:
  - `--password`, `--ocr-server-header` and `--api-key` values reach logs in clear text. Reproduced: `lit parse … --password hunter2 --ocr-server-header Authorization: Bearer tok` and `anydoc - --ocr hosted --api-key sk-secret`.
  - The sibling `ParseFailedException::fromResult()` (`src/Exceptions/ParseFailedException.php:31`) masks these values.
  - README.md:318 promises the `command` is safe to log.
  - Fix (Substitute Algorithm): run `$command` through `CommandRedactor::redact()` inside `after()`, so both named constructors are covered. Mark `fromResult`'s `$result` and `after`'s `$command` `#[SensitiveParameter]`, and add a test that asserts the mask on the timeout path.
- [x] 2. `Source::fromBytes()` lets the extension write outside the temp directory. It only trims, lowercases and strips the leading dot of the extension (`src/Source.php:31-35`). `NativeFilesystem::temporaryPath()` then builds `'parsel_'.uniqid('', true).'.'.$extension` (`src/Support/NativeFilesystem.php`), and `CliProcess::resolveFile()` writes the bytes there (`src/Support/CliProcess.php:71-72`).
  - Reproduced: `Parsel::bytes('PAYLOAD', 'pdf/../../<dir>/victim.txt')->text()` created and overwrote `victim.txt`.
  - The `finally` cleanup then fails silently and leaves the file behind.
  - An app that derives the extension from an upload's filename gets an arbitrary-file-write primitive.
  - Fix (Introduce Assertion): in `fromBytes()`, reject any extension that doesn't match `/^[a-z0-9]+(?:\.[a-z0-9]+)*$/`, throwing the same `InvalidArgumentException` it already throws for an empty one.

## P1: Misleading

- [x] 3. `LiteParseDriver::pages()` (`src/Drivers/LiteParseDriver.php:142-155`) passes the `-o` temp path straight to `Items::fromFile()` without checking that the file exists. `decodeJson()` (`:244-246`) does check, and throws `InvalidOutputException::emptyOutput`. When `lit` exits 0 without writing the file, `lazyPages()` instead emits an `fopen` warning and then a `TypeError` from `fclose()`, which `catch (ParselException)` does not catch.
  - The shipped `FakeProcessRunner` (`src/Support/FakeProcessRunner.php:39-45`) never writes `-o` files. So `lazyPages()` always fails this way under the documented `Parsel::fake()`, while `parse()` works with the same fake. The repo's own tests avoid this with the private `tests/Doubles/FakeJsonOutputRunner.php`.
  - Fix (Introduce Assertion): after `process->run()` in `pages()`, throw `InvalidOutputException::emptyOutput($this->name())` when `! $this->files->exists($output)`.
  - Optionally, an additive change: have `FakeProcessRunner` write a string response to the path that follows `-o`/`--output`, so `lazyPages()` can be faked.
- [x] 4. `PendingParse::lazyPages()` (`src/PendingParse.php:125-133`) contains `yield from`, which makes the method a generator, so its capability check doesn't run until the first iteration.
  - `Parsel::driver('anydoc')->bytes('x', 'pdf')->lazyPages()` returns a `Generator`. The `UnsupportedCapabilityException`, and the `SourceNotFoundException` for a missing file, are only thrown inside the `foreach`. A `try` around the call misses them, unlike for `text()`, `parse()` and `screenshots()`.
  - The test hides this by wrapping the call in `iterator_to_array` (`tests/Unit/PendingParseTest.php:76-77`).
  - Fix (Extract Method): make `lazyPages()` a plain method that checks the capability, then `return $this->driver->pages($this->request());`. The signature and return type are unchanged.
- [x] 5. The README "Testing" section (README.md:333-355) is incomplete in two ways.
  - Its example fails as written. `Parsel::fake()` replaces only the `ProcessRunner`, but path sources are still checked against the real filesystem (`src/Source.php:55-67`). So `Parsel::file('invoice.pdf')` throws `SourceNotFoundException` and `ranCount()` stays `0` (reproduced).
  - It never mentions `Parsel::flush()` (`src/Parsel.php:79-86`). That is the only reset for the static fake, default driver, timeout and extensions, and the repo's own suite needs it (`tests/Pest.php:14-26`). Without it, a fake installed in one test silently carries over into every later test in the same process.
  - Fix (docs only): use `Parsel::bytes(...)` in the example (or say the file must exist), and add "call `Parsel::flush()` in your test teardown".
- [x] 6. README.md:288 says "All exceptions extend `ParselException`", which isn't true.
  - `Source::fromPath()`/`fromBytes()` throw plain `\InvalidArgumentException` (`src/Source.php:23`, `:34`).
  - An invalid timeout surfaces as Symfony's `Process\Exception\InvalidArgumentException` (see item 8).
  - `LiteParseOptions::withImages('bogus')` throws a raw `ValueError` (`src/Options/LiteParseOptions.php:132`).
  - Fix (docs only, non-breaking): say "Parser, I/O and option failures extend `ParselException`; invalid arguments throw `\InvalidArgumentException` or `\ValueError`." Changing the thrown types would break existing `catch` blocks, so leave the code as is.

## P2: Inconsistent or costly to change

- [x] 7. AnyDoc still sends `--api-key` and `--api-url` when OCR is rejected. `AnyDocOptions::ocr(Reject)` clears those keys only inside its own object (`src/Options/AnyDocOptions.php:45-47`). `AnyDocDriver::command()` (`src/Drivers/AnyDocDriver.php:109-116`) appends whichever keys are present, in any OCR mode.
  - `withProviderOptions(AnyDocOptions::make()->withHostedOcr('sk'))->withProviderOptions(AnyDocOptions::make()->rejectOcr())` runs `anydoc - --ocr reject --api-key sk`. `PendingParse` uses `array_replace` and keeps the earlier key, so the secret lands in the process argv after the user opted out.
  - LiteParse only emits its OCR-only flags inside the `ocr === true` branch (`src/Drivers/LiteParseDriver.php:284-291`).
  - Fix (Consolidate Conditional Expression): in `AnyDocDriver::command()`, append `--api-key`/`--api-url` only when `$options['ocr'] === AnyDocOcrMode::Hosted->value`. anydoc ignores these flags outside hosted mode, so behaviour is otherwise unchanged.
- [x] 8. Timeouts are never validated. `PendingParse::withTimeout()` (`src/PendingParse.php:64-69`) and `Parsel::defaultTimeout()` (`src/Parsel.php:58-62`) store any float.
  - Symfony treats `0` as "no timeout" and throws on a negative value, but only when the process starts.
  - `defaultTimeout(-5)` therefore fails on every later parse, far from the bad call.
  - Custom drivers receive the raw `ParseRequest::$timeout` and have to guess these rules.
  - Fix (Introduce Assertion): throw `\InvalidArgumentException` for negative values in both setters. Symfony's exception already extends it, so callers' `catch` blocks still match. Document in README.md:128 that `0`, like `null`, disables the timeout.
- [x] 9. `PendingParse::withProviderOptions()` (`src/PendingParse.php:45-59`) merges options inconsistently.
  - String `pages` values are joined with `,`, but an int value replaces the earlier pages: `['pages' => '1']` followed by `['pages' => 2]` sends `--target-pages 2` (reproduced).
  - `extra` and `screenshot_extra` are merged key by key, but LiteParse's own map option `ocr_server_headers` is replaced wholesale. `['ocr_server_headers' => ['A' => '1']]` followed by `['ocr_server_headers' => ['B' => '2']]` sends only `B: 2`, silently dropping an earlier auth header (reproduced).
  - These key rules also apply to third-party drivers, so a custom driver's `pages` string is rewritten too.
  - Fix (Consolidate Conditional Expression):
    - Cast int `pages` to string before joining.
    - Add `ocr_server_headers` to the map-merge key list.
    - Document in the README "Custom Drivers" section that the `pages`, `extra` and `screenshot_extra` keys are merged.
- [x] 10. Extension factories receive a `ParselManager` they can't use. The factory signature is `Closure(ParselManager): Driver` (`src/ParselManager.php:58-59`, `src/Parsel.php:51-52`), but the manager's `$process` and `$files` are private (`src/ParselManager.php:27-34`) and only the built-in drivers are wired to them (`:84-97`).
  - A third-party CLI driver can't reuse the runner installed by `Parsel::fake()`/`swap()`, so its tests spawn real processes.
  - It also can't share the `Filesystem` that `save()` and the bundled drivers use.
  - Fix (Introduce Local Extension, i.e. complete the library class): add read-only `processRunner(): ProcessRunner` and `filesystem(): Filesystem` accessors to `ParselManager`. This is additive.

## P3: Polish

### Duplicates to remove

- [x] 11. The two drivers duplicate their option and flag plumbing:
  - Unknown-key validation: `src/Drivers/LiteParseDriver.php:69-73` and `src/Drivers/AnyDocDriver.php:47-51`.
  - `--flag value` appending: `LiteParseDriver::appendFlag()` (`:339-347`) and the loop in `AnyDocDriver::command()` (`:109-116`).
  - String-option reads: `LiteParseDriver::string()` (`:378-383`) and the inline `is_string` copies at `AnyDocDriver.php:62`, `:63` and `:112`.
  - Binary resolution, three times in LiteParse (`:103`, `:144`, `:170`) and as an inline variant in `AnyDocDriver.php:61-62`.

  Item 7's guard, or any change to how flags are built, must touch all of these.
  - Fix (Extract Method):
    - Move `appendFlag()` into the existing `@internal` `Support\CliArguments` and reuse it in `AnyDocDriver::command()`.
    - Add a private `binary(array $options)` helper for LiteParse's three call sites.
    - Only private or `@internal` code changes.
- [x] 12. The default timeout `60.0` is repeated in nine places, and the default driver `'liteparse'` in three:
  - `60.0`: `src/Parsel.php:25`, `src/Parsel.php:84`, `src/ParselManager.php:31`, `src/Parser.php:15`, `src/PendingParse.php:29`, `src/ParseRequest.php:15`, `src/Contracts/ProcessRunner.php:15`, `src/Support/FakeProcessRunner.php:23`, `src/Support/SymfonyProcessRunner.php:16`.
  - `'liteparse'`: `src/Parsel.php:23`, `src/Parsel.php:83`, `src/ParselManager.php:30`.

  Changing the documented default means editing every one of them, and missing one gives different defaults depending on the entry point.
  - Fix (Replace Magic Number with Symbolic Constant): add `ParselManager::DEFAULT_TIMEOUT` and `ParselManager::DEFAULT_DRIVER`, and reference them in these defaults. The values don't change.

### Primitive values

- [x] 13. Malformed nullable DTO fields become `''` or `0.0` instead of `null`. `TextItem::fromArray()` (`src/Data/TextItem.php:34-36`) passes `fontName` and `confidence` through `Cast::str`/`Cast::float` (`src/Data/Cast.php:12-25`), which return `''`/`0.0` for the wrong type.
  - `TextItem::fromArray(['fontName' => 123, 'confidence' => 'n/a'])` yields `font_name: ""` and `confidence: 0.0`: a confident zero instead of "unknown".
  - Fix (Substitute Algorithm): use `is_string($v) ? $v : null` for the string field and `is_numeric($v) ? (float) $v : null` for the numeric fields. Only malformed input changes.

### Error messages

- [x] 14. `LiteParseDriver::screenshots()` checks `exists()`, not that the target is a directory (`src/Drivers/LiteParseDriver.php:98`).
  - `screenshots('composer.json')` fails with `FilesystemException: Unable to write to "composer.json/page_1.png"` instead of `directoryNotFound`.
  - Fix (Introduce Assertion): check `is_dir($directory)`. The exception class is the same; only the named constructor is more accurate.
