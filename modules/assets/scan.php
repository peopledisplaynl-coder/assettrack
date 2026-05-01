<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$asset = null;
$error = null;
$id = (int)($_GET['id'] ?? 0);
$assetNumber = trim($_GET['asset'] ?? '');

if ($id > 0) {
    $asset = getAssetById($id);
} elseif ($assetNumber !== '') {
    $asset = queryOne(
        "SELECT a.*, l.name as location_name, o.name as org_name
         FROM assets a
         LEFT JOIN locations l ON a.location_id = l.id
         LEFT JOIN organisations o ON l.organisation_id = o.id
         WHERE a.asset_number = ?",
        [$assetNumber]
    );
}

if (!$asset) {
    $error = 'Asset niet gevonden.';
}

$canEdit = isLoggedIn() && hasPermission('edit_assets');
$showPhotos = isLoggedIn() && $asset && !empty(getAssetImages($asset['id']));
$assetTitle = $asset['asset_number'] ?? 'AssetTrack';
?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($assetTitle) ?> — AssetTrack Scan</title>
    <meta name="theme-color" content="#1a2332">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1f2937; }
        .mobile-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: #1a2332; color: white; padding: 16px 18px; display: flex; align-items: center; justify-content: space-between; }
        .topbar a { color: white; text-decoration: none; font-weight: 600; }
        .content { padding: 20px; flex: 1; }
        .card { background: white; border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,0.08); padding: 20px; margin-bottom: 18px; }
        .status-badge { display: inline-flex; padding: 10px 14px; border-radius: 999px; font-weight: 700; color: white; margin-bottom: 18px; }
        .status-In\ gebruik { background: #10b981; }
        .status-Beschikbaar { background: #3b82f6; }
        .status-In\ reparatie { background: #f59e0b; }
        .status-Buiten\ gebruik { background: #ef4444; }
        .status-Afgevoerd { background: #6b7280; }
        .field-row { display: grid; gap: 6px; margin-bottom: 14px; }
        .field-label { color: #6b7280; font-size: 0.85rem; }
        .field-value { font-size: 1.05rem; font-weight: 600; }
        .button-row { display: grid; gap: 12px; margin-top: 18px; }
        .btn-mobile { display: inline-flex; justify-content: center; align-items: center; padding: 14px; border-radius: 14px; text-decoration: none; color: white; font-weight: 700; }
        .btn-primary { background: #2563eb; }
        .btn-secondary { background: #4b5563; }
        .link-small { display: block; text-align: center; margin-top: 12px; color: #2563eb; text-decoration: none; font-weight: 600; }
        .error-card { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
<div class="mobile-shell">
    <header class="topbar">
        <div><strong>AssetTrack</strong></div>
        <div>
            <?php if (isLoggedIn()): ?>
                <a href="<?= BASE_URL ?>/logout.php">Uitloggen</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/index.php">Inloggen</a>
            <?php endif; ?>
        </div>
    </header>
    <main class="content">
        <?php if ($error): ?>
            <div class="card error-card">
                <h2>Fout</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="<?= BASE_URL ?>/modules/assets/" class="btn-mobile btn-secondary">Naar overzicht</a>
            </div>
        <?php else: ?>
            <div class="card">
                <?php
                $statusColors = [
                    'In gebruik'     => '#10b981',
                    'Beschikbaar'    => '#3b82f6',
                    'In reparatie'   => '#f59e0b',
                    'Buiten gebruik' => '#ef4444',
                    'Afgevoerd'      => '#6b7280',
                ];
                $statusColor = $statusColors[$asset['status'] ?? ''] ?? '#6b7280';
                ?>
                <div class="status-badge" style="background:<?= $statusColor ?>;">
                    <?= htmlspecialchars($asset['status'] ?: 'Beschikbaar') ?>
                </div>
                <div class="field-row">
                    <div class="field-label">Assetnummer</div>
                    <div class="field-value"><?= htmlspecialchars($asset['asset_number'] ?? '-') ?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">Merk</div>
                    <div class="field-value"><?= htmlspecialchars($asset['brand'] ?: '-') ?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">Model</div>
                    <div class="field-value"><?= htmlspecialchars($asset['model'] ?: '-') ?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">Ruimte</div>
                    <div class="field-value"><?= htmlspecialchars($asset['room'] ?: '-') ?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">Locatie</div>
                    <div class="field-value"><?= htmlspecialchars($asset['location_name'] ?: '-') ?></div>
                </div>
                <?php if (isLoggedIn()): ?>
                <div class="field-row">
                    <div class="field-label">In gebruik bij</div>
                    <div class="field-value"><?= htmlspecialchars($asset['assigned_to'] ?: '-') ?></div>
                </div>
                <?php endif; ?>
                <div class="button-row">
                    <?php if ($canEdit): ?>
                        <a href="<?= BASE_URL ?>/modules/assets/edit.php?id=<?= $asset['id'] ?>" class="btn-mobile btn-primary">Bewerken</a>
                    <?php endif; ?>
                    <?php if ($showPhotos): ?>
                        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>" class="btn-mobile btn-secondary">Foto&apos;s bekijken</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/modules/assets/" class="btn-mobile btn-secondary">Naar volledig overzicht</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
