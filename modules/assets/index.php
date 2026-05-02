<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePermission('view_assets');
requireLocation();

// Bulk actie verwerking
$bulkMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (hasPermission('edit_assets'))) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $bulkMessage = '<div class="alert alert-danger">Ongeldige CSRF token.</div>';
    } else {
        $assetIds = array_map('intval', $_POST['asset_ids'] ?? []);
        $action = $_POST['bulk_action'] ?? '';
        $actionValue = $_POST['action_value'] ?? '';

        if (!empty($assetIds) && !empty($action)) {
            $updatedCount = 0;

            if ($action === 'change_status' && $actionValue) {
                foreach ($assetIds as $id) {
                    execute("UPDATE assets SET status = ? WHERE id = ? AND location_id IN (SELECT id FROM locations WHERE id = ?)", 
                            [$actionValue, $id, getLocationId()]);
                    $updatedCount++;
                }
                $bulkMessage = "<div class=\"alert alert-success\">$updatedCount asset(s) status bijgewerkt.</div>";
            } elseif ($action === 'change_location' && $actionValue) {
                $newLocationId = (int)$actionValue;
                $userLocs = getUserLocations();
                $hasAccess = false;
                foreach ($userLocs as $loc) {
                    if ($loc['id'] === $newLocationId) {
                        $hasAccess = true;
                        break;
                    }
                }
                if ($hasAccess) {
                    foreach ($assetIds as $id) {
                        execute("UPDATE assets SET location_id = ? WHERE id = ? AND location_id IN (SELECT id FROM locations WHERE id = ?)", 
                                [$newLocationId, $id, getLocationId()]);
                        $updatedCount++;
                    }
                    $bulkMessage = "<div class=\"alert alert-success\">$updatedCount asset(s) verplaatst naar locatie.</div>";
                } else {
                    $bulkMessage = '<div class="alert alert-danger">Je hebt geen toegang tot deze locatie.</div>';
                }
            } elseif ($action === 'delete_assets') {
                if (!hasPermission('delete_assets')) {
                    $bulkMessage = '<div class="alert alert-danger">Geen rechten om assets te verwijderen.</div>';
                } else {
                    $deletedCount = 0;
                    foreach ($assetIds as $id) {
                        $asset = getAssetById($id);
                        if ($asset) {
                            // Verwijder ook de afbeeldingen
                            $images = getAssetImages($id);
                            foreach ($images as $image) {
                                deleteAssetImage($image['id'], $id);
                            }
                            deleteAsset($id);
                            logAudit('DELETE', 'assets', $id, $asset, null);
                            $deletedCount++;
                        }
                    }
                    $bulkMessage = "<div class=\"alert alert-success\">$deletedCount asset(s) verwijderd.</div>";
                }
            }
        } else {
            $bulkMessage = '<div class="alert alert-warning">Selecteer assets en een actie.</div>';
        }
    }
}

$search  = trim($_GET['search'] ?? '');
$filters = [
    'status' => $_GET['status'] ?? '',
    'type'   => $_GET['type'] ?? '',
    'room'   => $_GET['room'] ?? '',
];

// Paginering
$perPage     = (int)($_GET['per_page'] ?? 50);
$validPerPage = [25, 50, 100, 250, 500];
if (!in_array($perPage, $validPerPage)) $perPage = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Totaal aantal voor paginering (zonder limit)
$totalAssets = searchAssets($search, array_filter($filters), 99999, 0);
$totalCount  = count($totalAssets);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset      = ($currentPage - 1) * $perPage;

$assets = searchAssets($search, array_filter($filters), $perPage, $offset);

foreach ($assets as &$asset) {
    $asset = calculateAssetFields($asset);
}
unset($asset);

// Verwijder duplicaten op basis van id
$unique = [];
$seen   = [];
foreach ($assets as $asset) {
    if (!in_array($asset['id'], $seen)) {
        $seen[]   = $asset['id'];
        $unique[] = $asset;
    }
}
$assets = $unique;

$pageTitle = 'Assets';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Asset Overzicht</h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (hasPermission('add_assets')): ?>
        <a href="<?= BASE_URL ?>/modules/assets/add.php" class="btn btn-primary">+ Asset toevoegen</a>
        <?php endif; ?>
        <?php if (hasPermission('import_assets')): ?>
        <a href="<?= BASE_URL ?>/modules/assets/import.php" class="btn btn-secondary">📥 Importeren</a>
        <?php endif; ?>
        <?php if (hasPermission('edit_assets')): ?>
        <a href="<?= BASE_URL ?>/modules/assets/bulk_edit.php" class="btn btn-secondary">✏️ Bulk bewerken</a>
        <a href="<?= BASE_URL ?>/modules/assets/copy_asset.php" class="btn btn-secondary">📋 Kopiëren</a>
        <a href="<?= BASE_URL ?>/modules/assets/templates.php" class="btn btn-secondary">📦 Templates</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>

<?php if ($bulkMessage): ?>
<?= $bulkMessage ?>
<?php endif; ?>

<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET">
            <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:8px;">
                <input type="text" name="search" class="form-control"
                       placeholder="Zoek op nummer, merk, model..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-secondary">🔍</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;">
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
                <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="assetsForm">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <!-- Bulk action bar -->
    <div class="card" style="margin-bottom:15px;background:#f9fafb;">
        <div class="card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <span style="font-size:0.9rem;"><input type="checkbox" id="selectAll" style="cursor:pointer;"> <label for="selectAll" style="cursor:pointer;">Alles selecteren</label></span>
            <select name="bulk_action" class="form-control" style="width:200px;">
                <option value="">-- Bulkactie --</option>
                <option value="change_status">Status wijzigen</option>
                <option value="change_location">Locatie wijzigen</option>
                <?php if (hasPermission('delete_assets')): ?>
                <option value="delete_assets">Verwijderen</option>
                <?php endif; ?>
            </select>
            <select name="action_value" id="actionValue" class="form-control" style="width:200px;display:none;">
            </select>
            <button type="submit" class="btn btn-primary" style="display:none;" id="bulkSubmitBtn">Uitvoeren</button>
            <span id="selectedCount" style="color:#6b7280;font-size:0.9rem;">0 geselecteerd</span>
        </div>
    </div>

    <?php
    $statusColors = ['In gebruik'=>'badge-success','Beschikbaar'=>'badge-info',
                     'In reparatie'=>'badge-warning','Buiten gebruik'=>'badge-danger','Afgevoerd'=>'badge-secondary'];
    ?>

    <!-- Desktop tabel (verborgen op mobiel) -->
    <div class="card assets-table-wrap">
        <div class="card-body" style="overflow-x:auto;padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:36px;padding:10px 8px 10px 16px;">
                            <input type="checkbox" id="selectAllCheck" style="cursor:pointer;">
                        </th>
                        <th style="width:52px;"></th>
                        <th>Assetnummer</th>
                        <th>Soort</th>
                        <th>Merk / Model</th>
                        <th>Ruimte</th>
                        <th>Status</th>
                        <th>Leeftijd</th>
                        <th style="position:sticky;right:0;background:#f8fafc;box-shadow:-2px 0 4px rgba(0,0,0,0.06);">Acties</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($assets)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:40px;color:#6b7280;">
                        Geen assets gevonden.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($assets as $asset): ?>
                    <?php $mainImage = getAssetMainImage($asset['id']); ?>
                    <tr>
                        <td style="padding:10px 8px 10px 16px;">
                            <input type="checkbox" name="asset_ids[]" value="<?= $asset['id'] ?>"
                                   class="assetCheckbox" style="cursor:pointer;">
                        </td>
                        <td>
                            <?php if ($mainImage): ?>
                            <img src="<?= BASE_URL ?>/assets/uploads/asset_images/<?= htmlspecialchars($mainImage['filename']) ?>"
                                 style="width:38px;height:38px;object-fit:cover;border-radius:6px;display:block;">
                            <?php else: ?>
                            <div style="width:38px;height:38px;border-radius:6px;background:#e2e8f0;
                                        display:flex;align-items:center;justify-content:center;font-size:1rem;">📷</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>"
                               style="font-weight:700;color:var(--color-primary,#2563eb);text-decoration:none;">
                                <?= htmlspecialchars($asset['asset_number']) ?>
                            </a>
                            <?php if (!empty($asset['location_name'])): ?>
                            <div style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($asset['location_name']) ?></div>
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
                        <td><?= $asset['age_years'] ? number_format($asset['age_years'], 1) . ' jr' : '-' ?></td>
                        <td style="position:sticky;right:0;background:white;box-shadow:-2px 0 4px rgba(0,0,0,0.06);">
                            <div style="display:flex;gap:4px;padding:0 4px;">
                                <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>"
                                   class="btn btn-sm btn-secondary">Bekijken</a>
                                <?php if (hasPermission('edit_assets')): ?>
                                <a href="<?= BASE_URL ?>/modules/assets/edit.php?id=<?= $asset['id'] ?>"
                                   class="btn btn-sm btn-secondary">✏️</a>
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

    <!-- Mobiel kaartjes (verborgen op desktop) -->
    <div class="assets-cards-wrap">
        <?php if (empty($assets)): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:40px;color:#6b7280;">
                Geen assets gevonden.
            </div>
        </div>
        <?php else: ?>
        <div style="display:grid;gap:10px;">
            <?php foreach ($assets as $asset): ?>
            <?php $mainImage = getAssetMainImage($asset['id']); ?>
            <div class="card" style="margin:0;">
                <div class="card-body" style="padding:14px;">
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <!-- Checkbox + foto -->
                        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0;">
                            <input type="checkbox" name="asset_ids[]" value="<?= $asset['id'] ?>"
                                   class="assetCheckbox" style="cursor:pointer;width:16px;height:16px;">
                            <?php if ($mainImage): ?>
                            <img src="<?= BASE_URL ?>/assets/uploads/asset_images/<?= htmlspecialchars($mainImage['filename']) ?>"
                                 style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                            <div style="width:48px;height:48px;border-radius:8px;background:#e2e8f0;
                                        display:flex;align-items:center;justify-content:center;font-size:1.3rem;">📷</div>
                            <?php endif; ?>
                        </div>
                        <!-- Inhoud -->
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;margin-bottom:4px;">
                                <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>"
                                   style="font-weight:700;font-size:1rem;color:var(--color-primary,#2563eb);
                                          text-decoration:none;line-height:1.2;">
                                    <?= htmlspecialchars($asset['asset_number']) ?>
                                </a>
                                <span class="badge <?= $statusColors[$asset['status']] ?? 'badge-secondary' ?>">
                                    <?= htmlspecialchars($asset['status']) ?>
                                </span>
                            </div>
                            <div style="font-size:0.875rem;color:#374151;margin-bottom:2px;">
                                <strong><?= htmlspecialchars($asset['brand'] ?? '') ?></strong>
                                <?= htmlspecialchars($asset['model'] ?? '') ?>
                            </div>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:0.78rem;color:#6b7280;margin-bottom:10px;">
                                <?php if ($asset['type']): ?>
                                <span>📋 <?= htmlspecialchars($asset['type']) ?></span>
                                <?php endif; ?>
                                <?php if ($asset['room']): ?>
                                <span>📍 <?= htmlspecialchars($asset['room']) ?></span>
                                <?php endif; ?>
                                <?php if ($asset['age_years']): ?>
                                <span>🕐 <?= number_format($asset['age_years'], 1) ?> jr</span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>"
                                   class="btn btn-sm btn-secondary" style="flex:1;justify-content:center;">
                                    Bekijken
                                </a>
                                <?php if (hasPermission('edit_assets')): ?>
                                <a href="<?= BASE_URL ?>/modules/assets/edit.php?id=<?= $asset['id'] ?>"
                                   class="btn btn-sm btn-secondary">✏️</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginering -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                margin-top:14px;flex-wrap:wrap;gap:10px;">

        <!-- Teller + per pagina -->
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span style="color:#6b7280;font-size:0.85rem;">
                <?= $totalCount ?> asset(s) — pagina <?= $currentPage ?> van <?= $totalPages ?>
            </span>
            <div style="display:flex;align-items:center;gap:6px;">
                <label style="font-size:0.82rem;color:#6b7280;">Per pagina:</label>
                <select class="form-control" style="width:80px;padding:4px 8px;"
                        onchange="changePerPage(this.value)">
                    <?php foreach ([25,50,100,250,500] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <script>
            function changePerPage(val) {
                var url = new URL(window.location.href);
                url.searchParams.set('per_page', val);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            }
            </script>
        </div>

        <!-- Pagina knoppen -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
            <?php
            // Bouw query string
            $qp = array_filter($filters);
            if ($search) $qp['search'] = $search;
            $qp['per_page'] = $perPage;
            function pageUrl(array $qp, int $page): string {
                $qp['page'] = $page;
                return '?' . http_build_query($qp);
            }
            ?>
            <!-- Vorige -->
            <?php if ($currentPage > 1): ?>
            <a href="<?= pageUrl($qp, $currentPage-1) ?>"
               class="btn btn-sm btn-secondary">← Vorige</a>
            <?php endif; ?>

            <!-- Pagina nummers -->
            <?php
            $start = max(1, $currentPage - 2);
            $end   = min($totalPages, $currentPage + 2);
            if ($start > 1): ?><a href="<?= pageUrl($qp,1) ?>" class="btn btn-sm btn-secondary">1</a><?php endif;
            if ($start > 2): ?><span style="padding:0 4px;color:#94a3b8;">…</span><?php endif;
            for ($p = $start; $p <= $end; $p++):
            ?><a href="<?= pageUrl($qp,$p) ?>"
                 class="btn btn-sm <?= $p===$currentPage?'btn-primary':'btn-secondary' ?>"><?= $p ?></a><?php
            endfor;
            if ($end < $totalPages-1): ?><span style="padding:0 4px;color:#94a3b8;">…</span><?php endif;
            if ($end < $totalPages): ?><a href="<?= pageUrl($qp,$totalPages) ?>" class="btn btn-sm btn-secondary"><?= $totalPages ?></a><?php endif;
            ?>

            <!-- Volgende -->
            <?php if ($currentPage < $totalPages): ?>
            <a href="<?= pageUrl($qp, $currentPage+1) ?>"
               class="btn btn-sm btn-secondary">Volgende →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheck = document.getElementById('selectAllCheck');
    const selectAll = document.getElementById('selectAll');
    const assetCheckboxes = document.querySelectorAll('.assetCheckbox');
    const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
    const actionValueSelect = document.getElementById('actionValue');
    const bulkSubmitBtn = document.getElementById('bulkSubmitBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    const userLocations = <?= json_encode(getUserLocations()) ?>;
    const assetStatuses = <?= json_encode(getAssetStatuses()) ?>;

    function updateSelectedCount() {
        const selected = Array.from(assetCheckboxes).filter(cb => cb.checked).length;
        selectedCountSpan.textContent = selected + ' geselecteerd';
        selectAllCheck.checked = selected === assetCheckboxes.length && assetCheckboxes.length > 0;
        return selected;
    }

    selectAllCheck.addEventListener('change', function() {
        assetCheckboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    });

    selectAll.addEventListener('change', function() {
        assetCheckboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    });

    assetCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });

    bulkActionSelect.addEventListener('change', function() {
        const action = this.value;
        actionValueSelect.innerHTML = '';
        actionValueSelect.style.display = 'none';
        bulkSubmitBtn.style.display = 'none';
        bulkSubmitBtn.textContent = 'Uitvoeren';
        bulkSubmitBtn.className = 'btn btn-primary';

        if (action === 'change_status') {
            actionValueSelect.style.display = 'block';
            bulkSubmitBtn.style.display = 'inline-block';
            actionValueSelect.innerHTML = '<option value="">Selecteer status...</option>';
            Object.entries(assetStatuses).forEach(([val, lbl]) => {
                const opt = document.createElement('option');
                opt.value = val;
                opt.textContent = lbl;
                actionValueSelect.appendChild(opt);
            });
        } else if (action === 'change_location') {
            actionValueSelect.style.display = 'block';
            bulkSubmitBtn.style.display = 'inline-block';
            actionValueSelect.innerHTML = '<option value="">Selecteer locatie...</option>';
            userLocations.forEach(loc => {
                const opt = document.createElement('option');
                opt.value = loc.id;
                opt.textContent = loc.name;
                actionValueSelect.appendChild(opt);
            });
        } else if (action === 'delete_assets') {
            actionValueSelect.style.display = 'none';
            bulkSubmitBtn.style.display = 'inline-block';
            bulkSubmitBtn.textContent = 'Verwijderen';
            bulkSubmitBtn.className = 'btn btn-danger';
        }
    });

    // Bevestigingsmelding voor verwijderen
    document.getElementById('assetsForm').addEventListener('submit', function(e) {
        const action = bulkActionSelect.value;
        if (action === 'delete_assets') {
            const selected = updateSelectedCount();
            if (!confirm('Weet je zeker dat je ' + selected + ' asset(s) wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
                e.preventDefault();
            }
        }
    });
});
</script>

<style>
/* Tabel op desktop, kaartjes op mobiel */
.assets-cards-wrap { display: none; }
@media (max-width: 768px) {
    .assets-table-wrap { display: none; }
    .assets-cards-wrap { display: block; }
    .page-header { flex-direction: column; align-items: flex-start; }
    .page-header > div { flex-wrap: wrap; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
