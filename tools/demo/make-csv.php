<?php
// Writes tools/demo/demo_import.csv: a fictional CSV for trying out the import screen.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/DemoData.php';
file_put_contents(__DIR__ . '/demo_import.csv', (new DemoData(new DateTimeImmutable('2026-10-23')))->importCsv(40));
echo "tools/demo/demo_import.csv written\n";
