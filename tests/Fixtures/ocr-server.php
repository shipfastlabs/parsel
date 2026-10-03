<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    return;
}

$log = getenv('PARSEL_OCR_SERVER_LOG');

if (is_string($log) && $log !== '') {
    file_put_contents($log, json_encode($_POST).PHP_EOL.json_encode(['headers' => array_change_key_case(getallheaders())]).PHP_EOL, FILE_APPEND);
}

header('Content-Type: application/json');

echo json_encode([
    'results' => [
        ['text' => 'PARSELOCRMARKER', 'bbox' => [10, 10, 200, 40], 'confidence' => 0.99],
    ],
]);
