<?php
/**
 * Kennisbank asset zoekfunctie
 * Geeft meer resultaten terug dan de relations.php (geen limiet van 10)
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$search     = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type']   ?? '');
$filterBrand= trim($_GET['brand']  ?? '');
$locationId = getLocationId();

if (strlen($search) < 1 && !$filterType && !$filterBrand) {
    echo '[]'; exit;
}

$where  = ['1=1'];
$params = [];

// Locatie filter
if ($locationId && getRole() !== 'superadmin') {
    $where[]  = "a.location_id = ?";
    $params[] = $locationId;
}

// Zoektekst
if ($search) {
    $where[]  = "(a.asset_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.type LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

// Type filter
if ($filterType) {
    $where[]  = "a.type = ?";
    $params[] = $filterType;
}

// Merk filter
if ($filterBrand) {
    $where[]  = "a.brand LIKE ?";
    $params[] = "%$filterBrand%";
}

$whereClause = implode(' AND ', $where);

// Geen limiet — alle resultaten teruggeven
$results = query(
    "SELECT a.id, a.asset_number, a.brand, a.model, a.type, a.status, a.room,
            l.name as location_name
     FROM assets a
     LEFT JOIN locations l ON a.location_id = l.id
     WHERE $whereClause
     ORDER BY a.asset_number",
    $params
);

echo json_encode($results);
exit;
