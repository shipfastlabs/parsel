<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;

require __DIR__.'/../vendor/autoload.php';

$document = $argv[1] ?? __DIR__.'/docs/sample.docx';

if (! file_exists($document)) {
    fwrite(STDERR, "Usage: php examples/parse-docx.php /path/to/document.docx\n");

    exit(1);
}

echo "== Word document text ==\n";
echo Parsel::file($document)->withoutOcr()->text()."\n\n";

echo "== Word document metadata ==\n";
$parsed = Parsel::file($document)->withoutOcr()->parse();
printf("pages=%d, characters=%d\n", $parsed->pageCount(), strlen($parsed->text));
