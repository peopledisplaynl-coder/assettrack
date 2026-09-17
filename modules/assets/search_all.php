<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePermission('view_assets');

// Deze pagina doorzoekt bewust ALLE locaties waartoe je toegang hebt, los van
// de locatie die je bovenin de header hebt geselecteerd. Alleen nuttig (en
// toegestaan) voor wie toegang heeft tot meer dan 1 locatie - een superadmin
// ziet hier dus altijd alle locaties; iemand met maar 1 locatie heeft aan de
// gewone Assets-pagina genoeg en wordt daar automatisch naar teruggestuurd.
$userLocations = getUserLocations();
if (count($userLocations) <= 1) {
    header('Location: ' . BASE_URL . '/modules/assets/');
    exit;
}
$accessibleLocationIds = array_map(fn($l) => (int)$l['id'], $userLocations);

$search  = trim($_GET['search'] ?? '');
$filters = [
    'status' => $_GET['status'] ?? '',
    'type'   => $_GET['type'] ?? '',
];

$requestedLocationId = (int)($_GET['location_id'] ?? 0);
if ($requestedLocationId && in_array($requestedLocationId, $accessibleLocationIds, true)) {
    $locationIds = [$requestedLocationId];
} else {
    $requestedLocationId = 0;
    $locationIds = $accessibleLocationIds;
}

// Paginering
$perPage      = (int)($_GET['per_page'] ?? 50);
$validPerPage = [25, 50, 100, 250, 500];
if (!in_array($perPage, $validPerPage)) $perPage = 50;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));

$totalAssets = searchAssets($search, array_filter($filters), 99999, 0, $locationIds);
$totalCount  = count($totalAssets);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset      = ($currentPage - 1) * $perPage;

$assets = searchAssets($search, array_filter($filters), $perPage, $offset, $locationIds);
foreach ($assets as &$asset) {
    $asset = calculateAssetFields($asset);
}
unset($asset);

$pageTitle = 'Alle locaties';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>🌐 Assets zoeken — alle locaties</h1>
    <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug naar Assets</a>
</div>

<div class="alert alert-info" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;">
    Dit overzicht doorzoekt <strong>alle locaties</strong> waartoe je toegang hebt, los van de locatie die je
    bovenin hebt geselecteerd. Zo vind je een asset ook als het in een andere locatie staat dan je nu geselecteerd
    hebt, en kun je 'm meteen bekijken, bewerken (ook verplaatsen naar een andere locatie) of verwijderen.
</div>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET">
            <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:8px;">
                <input type="text" name="search" class="form-control"
                       placeholder="Zoek op nummer, merk, model, serienummer, gebruiker..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-secondary">🔍</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;">
                <select name="location_id" class="form-control">
                    <option value="">Alle locaties</option>
                    <?php foreach ($userLocations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= $requestedLocationId === (int)$loc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(($loc['org_name'] ?? '') . ' — ' . $loc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control">
                    <option value="">Alle statussen</option>
                    <?php foreach (getAssetStatuses() as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-control">
                    <option value="">Alle soorten</option>
                    <?php foreach (getAssetTypes() as $t): ?>
                    <option value="<?= htmlspecialchars($t['name']) ?>" <?= $filters['type'] === $t['name'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="<?= BASE_URL ?>/modules/assets/search_all.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php
$statusColors = ['In gebruik'=>'badge-success','Beschikbaar'=>'badge-info',
                 'In reparatie'=>'badge-warning','Buiten gebruik'=>'badge-danger','Afgevoerd'=>'badge-secondary'];
?>

<div class="card">
    <div class="card-body" style="overflow-x:auto;padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Assetnummer</th>
                    <th>Locatie</th>
                    <th>Soort</th>
                    <th>Merk / Model</th>
                    <th>Ruimte</th>
                    <th>Status</th>
                    <th style="position:sticky;right:0;background:#f8fafc;box-shadow:-2px 0 4px rgba(0,0,0,0.06);">Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($assets)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">
                    Geen assets gevonden.
                </td></tr>
            <?php else: ?>
                <?php foreach ($assets as $asset): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>"
                           style="font-weight:700;color:var(--color-primary,#2563eb);text-decoration:none;">
                            <?= htmlspecialchars($asset['asset_number']) ?>
                        </a>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($asset['location_name'] ?? '-') ?></div>
                        <?php if (!empty($asset['org_name'])): ?>
                        <div style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($asset['org_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($asset['type'] ?? '') ?></td>
                    <td>
                        <span style="font-weight:600;"><?= htmlspecialchars($asset['brand'] ?? '') ?></span>
                        <?php if ($asset['model']): ?>
                        <span style="color:#6b7280;"> <?= htmlspecialchars($asset['model']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($asset['room'] ?? '') ?></td>
                    <td>
                        <span class="badge <?= $statusColors[$asset['status']] ?? 'badge-secondary' ?>">
                            <?= htmlspecialchars($asset['status']) ?>
                        </span>
                    </td>
                    <td style="position:sticky;right:0;background:white;box-shadow:-2px 0 4px rgba(0,0,0,0.06);">
                        <div style="display:flex;gap:4px;padding:0 4px;">
                            <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>"
                               class="btn btn-sm btn-secondary">Bekijken</a>
                            <?php if (hasPermission('edit_assets') && canEditLocation((int)$asset['location_id'])): ?>
                            <a href="<?= BASE_URL ?>/modules/assets/edit.php?id=<?= $asset['id'] ?>"
                               class="btn btn-sm btn-secondary" title="Bewerken / verplaatsen">✏️</a>
                            <?php endif; ?>
                            <?php if (hasPermission('delete_assets')): ?>
                            <a href="<?= BASE_URL ?>/modules/assets/delete.php?id=<?= $asset['id'] ?>"
                               class="btn btn-sm btn-secondary">Verwijderen</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:10px;">
    <span style="color:#6b7280;font-size:0.85rem;">
        <?= $totalCount ?> asset(s) — pagina <?= $currentPage ?> van <?= $totalPages ?>
    </span>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
        <?php
        $qp = array_filter($filters);
        if ($search) $qp['search'] = $search;
        if ($requestedLocationId) $qp['location_id'] = $requestedLocationId;
        $qp['per_page'] = $perPage;
        function pageUrlAllLocations(array $qp, int $page): string {
            $qp['page'] = $page;
            return '?' . http_build_query($qp);
        }
        $start = max(1, $currentPage - 2);
        $end   = min($totalPages, $currentPage + 2);
        if ($start > 1): ?><a href="<?= pageUrlAllLocations($qp,1) ?>" class="btn btn-sm btn-secondary">1</a><?php endif;
        if ($start > 2): ?><span style="padding:0 4px;color:#94a3b8;">…</span><?php endif;
        for ($p = $start; $p <= $end; $p++):
        ?><a href="<?= pageUrlAllLocations($qp,$p) ?>"
             class="btn btn-sm <?= $p===$currentPage?'btn-primary':'btn-secondary' ?>"><?= $p ?></a><?php
        endfor;
        if ($end < $totalPages-1): ?><span style="padding:0 4px;color:#94a3b8;">…</span><?php endif;
        if ($end < $totalPages): ?><a href="<?= pageUrlAllLocations($qp,$totalPages) ?>" class="btn btn-sm btn-secondary"><?= $totalPages ?></a><?php endif;
        ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
