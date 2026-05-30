<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;

require __DIR__.'/../vendor/autoload.php';

$pdf = __DIR__.'/docs/sample.pdf';

echo "== 1. text() ==\n";
$text = Parsel::file($pdf)->page(2)->withoutOcr()->text();
echo $text."\n\n";

echo "== 2. parse() -> Document with coordinates ==\n";
$document = Parsel::file($pdf)->page(1)->withoutOcr()->parse();
printf("pages=%d, items on page 1=%d\n", $document->pageCount(), count($document->pages[0]->items));
$item = $document->pages[0]->items[0];
printf(
    "first item: %s @ (%.1f, %.1f)  font=%s size=%.1f confidence=%.2f\n\n",
    $item->text,
    $item->x,
    $item->y,
    $item->fontName ?? '?',
    $item->fontSize ?? 0.0,
    $item->confidence ?? 0.0,
);

echo "== 3. pageRange() + withDpi() ==\n";
$ranged = Parsel::file($pdf)->pageRange(1, 1)->withDpi(150)->withoutOcr()->text();
printf("text length=%d\n\n", strlen($ranged));

echo "== 4. toArray() ==\n";
$array = Parsel::file($pdf)->page(1)->withoutOcr()->toArray();
echo 'keys: '.implode(', ', array_keys($array))."\n\n";

echo "== 5. save() json + text ==\n";
$jsonOut = sys_get_temp_dir().'/parsel-apple-p1.json';
$textOut = sys_get_temp_dir().'/parsel-apple-p1.txt';
Parsel::file($pdf)->page(1)->withoutOcr()->save($jsonOut);
Parsel::file($pdf)->page(1)->withoutOcr()->save($textOut);
printf("%s (%d bytes), %s (%d bytes)\n\n", $jsonOut, filesize($jsonOut), basename($textOut), filesize($textOut));

echo "== 6. screenshots() ==\n";
$shotDir = sys_get_temp_dir().'/parsel-shots';
is_dir($shotDir) || mkdir($shotDir);
$shots = Parsel::file($pdf)->pageRange(1, 5)->screenshots($shotDir);
echo 'generated: '.implode(', ', array_map(static fn (string $path): string => $path, $shots))."\n\n";

echo "== 7. bytes() input ==\n";
$bytes = (string) file_get_contents($pdf);
$fromBytes = Parsel::bytes($bytes, 'pdf')->page(1)->withoutOcr()->text();
printf("text length=%d\n\n", strlen($fromBytes));

echo "== 8. option() escape hatch ==\n";
$viaOption = Parsel::file($pdf)->page(1)->withoutOcr()->option('max-pages', 1)->text();
printf("text length=%d\n", strlen($viaOption));
