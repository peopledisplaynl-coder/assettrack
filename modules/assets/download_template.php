<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Haal custom velden op
$customFields = query("SELECT field_name, field_label FROM custom_fields WHERE active = 1 ORDER BY sort_order");

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="import_template.csv"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// UTF-8 BOM voor Excel
echo "\xEF\xBB\xBF";

// Alle standaard kolomnamen
$columns = [
    'Assetnummer',
    'Merk',
    'Model',
    'Soort',
    'Ruimte',
    'Serienummer',
    'Status',
    'In gebruik bij',
    'Meest recente gebruiker',
    'Aankoopdatum',
    'Garantie tot',
    'Afschrijving jaren',
    'Advies vervangingsdatum',
    'Autoupdate vervalt',
    'Geinstalleerd op',
    'Besturingssysteem',
    'RAM',
    'CPU',
    'MAC adres',
    'LAN IP',
    'Management IP',
    'Accesspoint nummer',
    'Telefoonnummer',
    'Monitor type',
    'Aantal monitoren',
    'Serienummer monitor',
    'Fabrikant URL',
    'Bedrijfskritisch',
    'Opmerking',
];

// Voeg custom velden toe
foreach ($customFields as $cf) {
    $columns[] = $cf['field_label'];
}

// Schrijf kolomrij
echo implode(';', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $columns)) . "\r\n";

// Voorbeeldrij 1
$example1 = [
    'AT-TEST01', 'Dell', 'Latitude 5520', 'Laptop', 'Kantoor 1',
    'SN123456', 'In gebruik', 'Jan Jansen', 'Piet de Vries',
    '01-01-2022', '01-01-2025', '5', '01-01-2027', '01-01-2026',
    '15-03-2022', 'Windows 11', '16GB', 'Intel i7',
    'AA:BB:CC:DD:EE:FF', '192.168.1.100', '10.0.0.1',
    'AP-01', '', 'Full HD', '1', 'MON-SN-001',
    'https://www.dell.com', '0', 'Testimport rij 1',
];
// Lege waarden voor custom velden
foreach ($customFields as $cf) {
    $example1[] = '';
}
echo implode(';', array_map(fn($c) => '"' . str_replace('"', '""', (string)$c) . '"', $example1)) . "\r\n";

// Voorbeeldrij 2
$example2 = [
    '', 'HP', 'EliteBook 840', 'Laptop', 'Directiekantoor',
    'SN789012', 'Beschikbaar', '', '',
    '', '', '3', '', '',
    '', 'Windows 10', '8GB', 'Intel i5',
    '', '', '',
    '', '', '', '', '',
    '', '0', '',
];
foreach ($customFields as $cf) {
    $example2[] = '';
}
echo implode(';', array_map(fn($c) => '"' . str_replace('"', '""', (string)$c) . '"', $example2)) . "\r\n";

exit;
