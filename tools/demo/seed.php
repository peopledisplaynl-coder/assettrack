<?php
/**
 * Fill a database with the fictional demo school (see DemoData.php).
 *
 *   ASSETTRACK_CONFIG=/path/to/demo-config.php php tools/demo/seed.php --force
 *
 * DESTRUCTIVE: drops every table in the configured database first. Refuses to
 * run unless --force is given, and refuses when the database name does not
 * contain "demo" or "test" (protection against pointing it at a live database).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
define('ASSETTRACK_SKIP_AUTO_MIGRATE', true);
require $root . '/includes/db.php';
require __DIR__ . '/DemoData.php';

$args = array_slice($argv, 1);
if (!in_array('--force', $args, true)) {
    fwrite(STDERR, "Refusing to run without --force (this wipes the database).\n");
    exit(1);
}
if (!preg_match('/demo|test/i', DB_NAME)) {
    fwrite(STDERR, "Refusing: database name '" . DB_NAME . "' does not contain 'demo' or 'test'.\n");
    exit(1);
}

$pdo = getDB();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec('DROP TABLE `' . str_replace('`', '', $table) . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Schema exactly like a fresh install.
foreach (array_filter(array_map('trim', explode(';', file_get_contents($root . '/install/install.sql')))) as $sql) {
    $pdo->exec($sql);
}
runMigrations($pdo);

$demo = new DemoData();
$ins = function (string $table, array $row) use ($pdo): int {
    $cols = array_keys($row);
    $stmt = $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . rtrim(str_repeat('?,', count($cols)), ',') . ")");
    $stmt->execute(array_values($row));
    return (int)$pdo->lastInsertId();
};

// Organisation, company (branding) and locations.
$pdo->prepare("UPDATE organisations SET name=?, city='Lindewaard', email='ict@lindewaard.example', website='https://www.lindewaard.example' WHERE id=1")
    ->execute([DemoData::ORGANISATION]);
$pdo->prepare("UPDATE companies SET name=?, app_name='AssetTrack', city='Lindewaard', email='ict@lindewaard.example' WHERE id=1")
    ->execute([DemoData::ORGANISATION]);
$pdo->exec("DELETE FROM rooms");
$locationIds = [];
foreach (DemoData::LOCATIONS as $i => $loc) {
    if ($i === 0) {
        $pdo->prepare("UPDATE locations SET name=?, address=?, zip=?, city=?, theme_color=? WHERE id=1")
            ->execute([$loc['name'], $loc['address'], $loc['zip'], $loc['city'], $loc['color']]);
        $locationIds[$i] = 1;
    } else {
        $locationIds[$i] = $ins('locations', ['organisation_id' => 1, 'name' => $loc['name'], 'address' => $loc['address'],
            'zip' => $loc['zip'], 'city' => $loc['city'], 'theme_color' => $loc['color']]);
    }
    foreach ($demo->rooms($i) as $room) {
        $desc = str_starts_with($room, 'Groep 1') || str_starts_with($room, 'Groep 3') || str_starts_with($room, 'Groep 4') ? 'Begane grond'
              : (str_starts_with($room, 'Groep') ? '1e verdieping' : null);
        $ins('rooms', ['location_id' => $locationIds[$i], 'name' => $room, 'location_desc' => $desc]);
    }
}

// Lookup lists used by the demo assets.
foreach (['Touchscreen'] as $type) $pdo->prepare("INSERT IGNORE INTO asset_types (name) VALUES (?)")->execute([$type]);
foreach (['Prowise', 'Clevertouch'] as $brand) $pdo->prepare("INSERT IGNORE INTO brands (name) VALUES (?)")->execute([$brand]);

// Users.
$hash = password_hash(DemoData::PASSWORD, PASSWORD_DEFAULT);
$userIds = [];
foreach (DemoData::USERS as [$username, $name, $role, $email, $locs]) {
    $userIds[$username] = $ins('users', ['full_name' => $name, 'username' => $username, 'email' => $email,
        'password_hash' => $hash, 'role' => $role, 'active' => 1]);
    foreach ($locs ?: array_keys(DemoData::LOCATIONS) as $li) {
        $ins('user_locations', ['user_id' => $userIds[$username], 'location_id' => $locationIds[$li],
            'can_view' => 1, 'can_edit' => $role === 'visitor' ? 0 : 1]);
    }
}

// Custom fields.
$cfSubsidy = $ins('custom_fields', ['field_name' => 'subsidie', 'field_label' => 'Aangeschaft met subsidie', 'field_type' => 'boolean', 'sort_order' => 1]);
$cfLease   = $ins('custom_fields', ['field_name' => 'leasecontract', 'field_label' => 'Leasecontract', 'field_type' => 'text', 'sort_order' => 2]);

// Assets.
$assetIds = [];
$counter = 0;
$columns = ['room', 'brand', 'model', 'type', 'serial_number', 'status', 'assigned_to', 'installed_date', 'purchase_date',
    'warranty_end_date', 'depreciation_years', 'autoupdate_expiry', 'mac_address', 'lan_ip_address', 'management_ip',
    'most_recent_user', 'notes', 'touchscreen_monitor_type', 'monitor_count', 'in_repair_since', 'out_of_service_since',
    'ram', 'cpu', 'operating_system', 'business_critical', 'phone_number', 'access_point_number', 'manufacturer_url'];
$brandUse = [];
foreach ($demo->assets() as $idx => $a) {
    $row = ['asset_number' => sprintf('AT-%05d', ++$counter), 'location_id' => $locationIds[$a['_loc']],
        'created_by' => $userIds['demo'], 'registration_date' => ($a['purchase_date'] ?? date('Y-m-d')) . ' 10:00:00'];
    foreach ($columns as $c) {
        if (array_key_exists($c, $a) && $a[$c] !== null) $row[$c] = $a[$c];
    }
    $assetIds[$idx] = $ins('assets', $row);
    $brandUse[$a['brand']] = ($brandUse[$a['brand']] ?? 0) + 1;
    if (isset($a['_links_to'])) {
        $ins('asset_relations', ['asset_id' => $assetIds[$a['_links_to']], 'related_id' => $assetIds[$idx], 'relation_type' => 'peripheral']);
    }
    if (isset($a['_subsidy'])) $ins('custom_field_values', ['asset_id' => $assetIds[$idx], 'field_id' => $cfSubsidy, 'value' => $a['_subsidy']]);
    if (!empty($a['_lease']))   $ins('custom_field_values', ['asset_id' => $assetIds[$idx], 'field_id' => $cfLease, 'value' => $a['_lease']]);
}
foreach ($brandUse as $brand => $n) {
    $pdo->prepare("UPDATE brands SET use_count = ? WHERE name = ?")->execute([$n, $brand]);
}

// Templates.
$ins('asset_templates', ['name' => 'Chromebook leerling', 'icon' => '💻', 'asset_type' => 'Chromebook', 'brand' => 'Acer',
    'model' => 'Chromebook Spin 512', 'depreciation_years' => 4, 'warranty_months' => 36, 'operating_system' => 'ChromeOS',
    'ram' => '4GB', 'cpu' => 'Intel Celeron N4500', 'manufacturer_url' => 'https://www.acer.com', 'created_by' => $userIds['demo']]);
$ins('asset_templates', ['name' => 'Laptop leerkracht', 'icon' => '🧑‍🏫', 'asset_type' => 'Laptop', 'brand' => 'HP',
    'model' => 'ProBook 440 G9', 'depreciation_years' => 4, 'warranty_months' => 36, 'operating_system' => 'Windows 11 Education',
    'ram' => '16GB', 'cpu' => 'Intel Core i5-1235U', 'created_by' => $userIds['demo']]);

// Knowledge base.
$kbCats = [];
foreach ([['Chromebooks', '💻', 'Beheer en problemen met Chromebooks'], ['Printers', '🖨️', 'Printen en scannen'],
          ['Netwerk & wifi', '📶', 'Wifi, accesspoints en bekabeling']] as $n => [$name, $icon, $desc]) {
    $kbCats[$name] = $ins('kb_categories', ['name' => $name, 'icon' => $icon, 'description' => $desc, 'sort_order' => $n]);
}
$ins('kb_articles', ['category_id' => $kbCats['Chromebooks'], 'title' => 'Chromebook herstellen naar fabrieksinstellingen (powerwash)',
    'content' => "1. Log uit op de Chromebook.\n2. Houd Ctrl + Alt + Shift + R ingedrukt.\n3. Kies Opnieuw opstarten en daarna Powerwash.\n4. Meld de Chromebook daarna opnieuw aan in het beheerdomein.",
    'asset_type' => 'Chromebook', 'tags' => 'reset,powerwash', 'created_by' => $userIds['demo']]);
$ins('kb_articles', ['category_id' => $kbCats['Printers'], 'title' => 'Papierstoring verhelpen',
    'content' => "Open de voorklep, verwijder het vastgelopen papier voorzichtig in de richting van de papierdoorvoer en sluit de klep. Lukt het niet? Meld het bij de ICT-coördinator.",
    'asset_type' => 'Printer', 'tags' => 'storing,papier', 'created_by' => $userIds['demo']]);
$ins('kb_articles', ['category_id' => $kbCats['Netwerk & wifi'], 'title' => 'Geen wifi in een lokaal',
    'content' => "Controleer of het lampje van het accesspoint wit brandt. Brandt het blauw of knippert het, herstart het accesspoint dan via de beheeromgeving.",
    'asset_type' => 'Netwerkapparatuur', 'tags' => 'wifi,accesspoint', 'created_by' => $userIds['r.verbeek']]);

// Some audit history for the dashboard's recent activity.
$recent = array_slice($assetIds, 0, 8);
foreach ($recent as $n => $id) {
    $ins('audit_log', ['user_id' => $n % 2 ? $userIds['r.verbeek'] : $userIds['demo'], 'action' => $n % 3 ? 'UPDATE' : 'CREATE',
        'table_name' => 'assets', 'record_id' => $id, 'new_values' => json_encode(['status' => 'In gebruik']),
        'ip_address' => '10.20.0.10', 'created_at' => date('Y-m-d H:i:s', time() - $n * 5400)]);
}

printf("Demo database ready: %d assets, %d locations, %d users (password: %s)\n",
    count($assetIds), count($locationIds), count($userIds), DemoData::PASSWORD);
