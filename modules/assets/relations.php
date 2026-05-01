<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('view_assets');

// JSON zoekfunctie voor autocomplete
if (isset($_GET['search'])) {
    header('Content-Type: application/json');
    $search     = trim($_GET['search'] ?? '');
    $excludeId  = (int)($_GET['exclude'] ?? 0);
    $locationId = getLocationId();

    if (strlen($search) < 2) {
        echo '[]'; exit;
    }

    $where  = ["(a.asset_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)"];
    $term   = "%$search%";
    $params = [$term, $term, $term];

    if ($excludeId) {
        $where[]  = "a.id != ?";
        $params[] = $excludeId;
    }
    if ($locationId && getRole() !== 'superadmin') {
        $where[]  = "a.location_id = ?";
        $params[] = $locationId;
    }

    $results = query(
        "SELECT a.id, a.asset_number, a.brand, a.model, a.type, a.status, a.room
         FROM assets a
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.asset_number LIMIT 10",
        $params
    );

    echo json_encode($results);
    exit;
}

// POST: koppeling toevoegen of verwijderen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('edit_assets');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: ' . BASE_URL . '/modules/assets/?error=CSRF');
        exit;
    }

    $action  = $_POST['action'] ?? '';
    $assetId = (int)($_POST['asset_id'] ?? 0);

    if ($action === 'add') {
        $relatedId    = (int)($_POST['related_id'] ?? 0);
        $relationType = $_POST['relation_type'] ?? 'peripheral';
        $notes        = trim($_POST['notes'] ?? '') ?: null;

        $validTypes = ['peripheral', 'child', 'replacement', 'network'];
        if (!in_array($relationType, $validTypes)) $relationType = 'peripheral';

        if ($assetId && $relatedId && $assetId !== $relatedId) {
            try {
                execute(
                    "INSERT IGNORE INTO asset_relations (asset_id, related_id, relation_type, notes)
                     VALUES (?, ?, ?, ?)",
                    [$assetId, $relatedId, $relationType, $notes]
                );
                logAudit('RELATE', 'asset_relations', $assetId,
                    null, ['related_id' => $relatedId, 'type' => $relationType]
                );
            } catch (Exception $e) {
                // Silently ignore duplicate
            }
        }
        $redirect = $_POST['redirect'] ?? 'view';
        $url = $redirect === 'edit'
            ? BASE_URL . '/modules/assets/edit.php?id=' . $assetId
            : BASE_URL . '/modules/assets/view.php?id=' . $assetId . '#relations';
        header('Location: ' . $url);
        exit;
    }

    if ($action === 'delete') {
        $relationId = (int)($_POST['relation_id'] ?? 0);
        if ($relationId) {
            execute("DELETE FROM asset_relations WHERE id = ?", [$relationId]);
            logAudit('UNRELATE', 'asset_relations', $assetId, ['relation_id' => $relationId], null);
        }
        $redirect = $_POST['redirect'] ?? 'view';
        $url = $redirect === 'edit'
            ? BASE_URL . '/modules/assets/edit.php?id=' . $assetId
            : BASE_URL . '/modules/assets/view.php?id=' . $assetId . '#relations';
        header('Location: ' . $url);
        exit;
    }
}

header('Location: ' . BASE_URL . '/modules/assets/');
exit;
