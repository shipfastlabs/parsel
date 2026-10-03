<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Support\CommandRedactor;

it('masks the value that follows a sensitive flag', function (string $flag): void {
    expect(CommandRedactor::redact(['lit', 'parse', 'doc.pdf', $flag, 'secret-value', '-q']))
        ->toBe(['lit', 'parse', 'doc.pdf', $flag, '********', '-q']);
})->with(CommandRedactor::SENSITIVE_FLAGS);

it('masks the inline --flag=value form', function (string $flag): void {
    expect(CommandRedactor::redact(['anydoc', 'doc.pdf', $flag.'=secret-value']))
        ->toBe(['anydoc', 'doc.pdf', $flag.'=********']);
})->with(CommandRedactor::SENSITIVE_FLAGS);

it('masks a header value containing spaces and colons', function (): void {
    expect(CommandRedactor::redact(['lit', '--ocr-server-header', 'Authorization: Bearer abc123']))
        ->toBe(['lit', '--ocr-server-header', '********']);
});

it('leaves non-sensitive arguments untouched', function (): void {
    $command = ['lit', 'parse', 'doc.pdf', '--format', 'json', '--password-file', 'x', '--ocr-server-url=http://ocr', '-q'];

    expect(CommandRedactor::redact($command))->toBe($command);
});

it('keeps a trailing sensitive flag without a value as is', function (): void {
    expect(CommandRedactor::redact(['lit', '--password']))->toBe(['lit', '--password']);
});

it('masks every occurrence', function (): void {
    expect(CommandRedactor::redact(['lit', '--password', 'a', '--api-key', 'b', '--password=c']))
        ->toBe(['lit', '--password', '********', '--api-key', '********', '--password=********']);
});
