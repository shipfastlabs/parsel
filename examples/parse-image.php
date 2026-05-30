<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;

require __DIR__.'/../vendor/autoload.php';

$image = $argv[1] ?? __DIR__.'/docs/sample.png';

if (! file_exists($image)) {
    fwrite(STDERR, "Usage: php examples/parse-image.php /path/to/image.png\n");

    exit(1);
}

echo "== Image OCR text ==\n";
echo Parsel::file($image)->withOcr(language: 'eng')->text()."\n\n";

echo "== Image OCR coordinates ==\n";
$document = Parsel::file($image)->withOcr(language: 'eng')->parse();

foreach ($document->pages as $page) {
    foreach (array_slice($page->items, 0, 10) as $item) {
        printf("%s @ (%.1f, %.1f)\n", $item->text, $item->x, $item->y);
    }
}
