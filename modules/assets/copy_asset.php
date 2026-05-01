<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');
requireLocation();

$errors  = [];
$success = '';

$sourceId     = (int)($_GET['source_id'] ?? $_POST['source_id'] ?? 0);
$sourceAsset  = $sourceId ? getAssetById($sourceId) : null;
$locationId   = getLocationId();

// Kopieerbare velden met labels
$copyableFields = [
    'brand'            => 'Merk',
    'type'             => 'Soort',
    'status'           => 'Status',
    'manufacturer_url' => 'Fabrikant URL',
    'depreciation_years' => 'Afschrijving (jaren)',
    'warranty_end_date'  => 'Einde garantie',
    'purchase_date'      => 'Aankoopdatum',
    'operating_system'   => 'Besturingssysteem',
    'ram'                => 'RAM',
    'cpu'                => 'CPU',
    'business_critical'  => 'Bedrijfskritisch',
    'room'               => 'Ruimte',
    'assigned_to'        => 'In gebruik bij',
    'notes'              => 'Opmerking',
    'autoupdate_expiry'  => 'Autoupdate vervalt',
    'touchscreen_monitor_type' => 'Monitor type',
    'monitor_count'      => 'Aantal monitoren',
];

// Verwerk kopieer actie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'copy') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $targetIds    = array_map('intval', $_POST['target_ids'] ?? []);
        $selectedCopy = $_POST['copy_fields'] ?? [];
        $srcId        = (int)($_POST['source_id'] ?? 0);
        $source       = getAssetById($srcId);

        if (!$source) {
            $errors[] = 'Bronasset niet gevonden.';
        } elseif (empty($targetIds)) {
            $errors[] = 'Geen doeласsets geselecteerd.';
        } elseif (empty($selectedCopy)) {
            $errors[] = 'Selecteer minimaal één veld om te kopiëren.';
        } else {
            $updatedCount = 0;
            foreach ($targetIds as $tid) {
                if ($tid === $srcId) continue; // sla bron over
                $updateData = [];
                foreach ($selectedCopy as $field) {
                    if (array_key_exists($field, $copyableFields)) {
                        $updateData[$field] = $source[$field] ?? null;
                    }
                }
                if (!empty($updateData)) {
                    $old = getAssetById($tid);
                    updateAsset($tid, $updateData);
                    logAudit('COPY_FROM', 'assets', $tid,
                        array_intersect_key($old, $updateData),
                        $updateData
                    );
                    $updatedCount++;
                }
            }
            $success = "$updatedCount asset(s) bijgewerkt met gegevens van " .
                       htmlspecialchars($source['asset_number']);
        }
    }
}

// Zoek doelassets
$filterSearch = trim($_GET['target_search'] ?? '');
$filterType   = trim($_GET['target_type'] ?? $sourceAsset['type'] ?? '');
$filterRoom   = trim($_GET['target_room'] ?? '');

$targetAssets = [];
if ($sourceAsset) {
    $where  = ["a.id != ?"];
    $params = [$sourceId];

    if ($locationId) {
        $where[]  = "a.location_id = ?";
        $params[] = $locationId;
    }
    if ($filterSearch) {
        $where[]  = "(a.asset_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)";
        $term     = "%$filterSearch%";
        $params   = array_merge($params, [$term, $term, $term]);
    }
    if ($filterType) {
        $where[]  = "a.type = ?";
        $params[] = $filterType;
    }
    if ($filterRoom) {
        $where[]  = "a.room = ?";
        $params[] = $filterRoom;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);
    $targetAssets = query("SELECT a.id, a.asset_number, a.brand, a.model, a.type, a.room, a.status
                           FROM assets a $whereClause
                           ORDER BY a.asset_number LIMIT 200", $params);
}

$allTypes = getAssetTypes();
$allRooms = getRoomsByLocation($locationId);

$pageTitle = 'Kopieer naar assets';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Kopieer velden naar meerdere assets</h1>
    <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- STAP 1: Kies bronasset -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
            Stap 1 — Kies bronasset
        </h3>
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                <label>Assetnummer of zoekterm</label>
                <input type="text" name="source_search" class="form-control"
                       placeholder="Zoek bronasset..."
                       value="<?= htmlspecialchars($_GET['source_search'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-secondary">Zoeken</button>
        </form>

        <?php
        $sourceSearch = trim($_GET['source_search'] ?? '');
        $sourceResults = [];
        if ($sourceSearch) {
            $swhere = $locationId ? "WHERE location_id = ? AND (asset_number LIKE ? OR brand LIKE ? OR model LIKE ?)" : "WHERE (asset_number LIKE ? OR brand LIKE ? OR model LIKE ?)";
            $sparams = $locationId ? [$locationId, "%$sourceSearch%", "%$sourceSearch%", "%$sourceSearch%"] : ["%$sourceSearch%", "%$sourceSearch%", "%$sourceSearch%"];
            $sourceResults = query("SELECT id, asset_number, brand, model, type, room FROM assets $swhere ORDER BY asset_number LIMIT 20", $sparams);
        }
        ?>

        <?php if (!empty($sourceResults)): ?>
        <div style="margin-top:15px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <table class="data-table" style="margin:0;">
                <thead><tr><th>Assetnummer</th><th>Merk</th><th>Model</th><th>Soort</th><th>Ruimte</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($sourceResults as $r): ?>
                <tr <?= $r['id'] === $sourceId ? 'style="background:#eff6ff;"' : '' ?>>
                    <td><strong><?= htmlspecialchars($r['asset_number']) ?></strong></td>
                    <td><?= htmlspecialchars($r['brand'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['model'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['type'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['room'] ?? '') ?></td>
                    <td>
                        <a href="?source_id=<?= $r['id'] ?>&source_search=<?= urlencode($sourceSearch) ?>&target_type=<?= urlencode($r['type'] ?? '') ?>"
                           class="btn btn-sm btn-primary">
                            <?= $r['id'] === $sourceId ? '✓ Geselecteerd' : 'Selecteer' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($sourceAsset): ?>
        <div style="margin-top:15px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;">
            <strong>Geselecteerde bronasset:</strong>
            <?= htmlspecialchars($sourceAsset['asset_number']) ?> —
            <?= htmlspecialchars(trim(($sourceAsset['brand'] ?? '') . ' ' . ($sourceAsset['model'] ?? ''))) ?>
            (<?= htmlspecialchars($sourceAsset['type'] ?? '') ?>)
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($sourceAsset): ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="action" value="copy">
    <input type="hidden" name="source_id" value="<?= $sourceId ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        <!-- STAP 2: Velden kiezen -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    Stap 2 — Kies te kopiëren velden
                </h3>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;
                               cursor:pointer;font-weight:600;color:#2563eb;">
                    <input type="checkbox" onchange="toggleAllFields(this)">
                    Alles selecteren
                </label>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($copyableFields as $fname => $flabel): ?>
                    <?php $val = $sourceAsset[$fname] ?? null; ?>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;
                                  padding:8px;border-radius:6px;border:1px solid #e5e7eb;">
                        <input type="checkbox" name="copy_fields[]"
                               value="<?= $fname ?>" class="fieldCb">
                        <span style="flex:1;">
                            <strong style="font-size:0.875rem;"><?= htmlspecialchars($flabel) ?></strong>
                            <span style="display:block;font-size:0.8rem;color:#6b7280;">
                                <?php if ($val === null || $val === ''): ?>
                                    <em>leeg</em>
                                <?php elseif ($fname === 'business_critical'): ?>
                                    <?= $val ? 'Ja' : 'Nee' ?>
                                <?php elseif (in_array($fname, ['purchase_date','warranty_end_date','advised_replacement_date','autoupdate_expiry'])): ?>
                                    <?= formatDate($val) ?: '—' ?>
                                <?php else: ?>
                                    <?= htmlspecialchars(mb_strimwidth((string)$val, 0, 50, '...')) ?>
                                <?php endif; ?>
                            </span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- STAP 3: Doeласsets -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    Stap 3 — Kies doeласsets
                </h3>

                <!-- Filter doelassets -->
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                    <input type="text" name="noop" id="targetSearch"
                           class="form-control" style="flex:1;"
                           placeholder="Zoek in resultaten..."
                           oninput="filterTargets(this.value)">
                    <select id="typeFilter" class="form-control" style="width:140px;"
                            onchange="filterTargets()">
                        <option value="">Alle soorten</option>
                        <?php foreach ($allTypes as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>"
                            <?= $filterType === $t['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;
                               cursor:pointer;font-weight:600;color:#2563eb;">
                    <input type="checkbox" id="selectAllTargets" onchange="toggleAllTargets(this)">
                    Alles selecteren
                    (<span id="targetCount"><?= count($targetAssets) ?></span>)
                </label>

                <div style="max-height:400px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
                    <?php if (empty($targetAssets)): ?>
                    <p style="padding:20px;color:#6b7280;text-align:center;">
                        Geen assets gevonden. Pas de filter aan.
                    </p>
                    <?php else: ?>
                    <table class="data-table" style="margin:0;">
                        <thead>
                            <tr><th width="36"></th><th>Nummer</th><th>Merk/Model</th><th>Ruimte</th></tr>
                        </thead>
                        <tbody id="targetList">
                            <?php foreach ($targetAssets as $ta): ?>
                            <tr class="target-row"
                                data-search="<?= strtolower(htmlspecialchars($ta['asset_number'] . ' ' . $ta['brand'] . ' ' . $ta['model'])) ?>"
                                data-type="<?= htmlspecialchars($ta['type'] ?? '') ?>">
                                <td>
                                    <input type="checkbox" name="target_ids[]"
                                           value="<?= $ta['id'] ?>"
                                           class="targetCb" checked>
                                </td>
                                <td><strong style="font-size:0.8rem;"><?= htmlspecialchars($ta['asset_number']) ?></strong></td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars(trim(($ta['brand'] ?? '') . ' ' . ($ta['model'] ?? ''))) ?></td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars($ta['room'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <p style="margin-top:8px;color:#6b7280;font-size:0.85rem;">
                    <span id="selectedTargetCount"><?= count($targetAssets) ?></span> geselecteerd
                </p>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;text-align:right;">
        <button type="submit" class="btn btn-primary"
                onclick="return confirmCopy()"
                style="padding:12px 28px;font-size:1rem;">
            📋 Kopieer naar geselecteerde assets
        </button>
    </div>
</form>

<script>
function toggleAllFields(cb) {
    document.querySelectorAll('.fieldCb').forEach(c => c.checked = cb.checked);
}
function toggleAllTargets(cb) {
    document.querySelectorAll('.targetCb:not([style*="display:none"])').forEach(c => {
        const row = c.closest('tr');
        if (row && row.style.display !== 'none') c.checked = cb.checked;
    });
    updateTargetCount();
}
function updateTargetCount() {
    const n = document.querySelectorAll('.targetCb:checked').length;
    document.getElementById('selectedTargetCount').textContent = n;
}
document.querySelectorAll('.targetCb').forEach(c => {
    c.addEventListener('change', updateTargetCount);
});
function filterTargets(val) {
    val = (val || document.getElementById('targetSearch').value).toLowerCase();
    document.querySelectorAll('.target-row').forEach(row => {
        const match = row.dataset.search.includes(val);
        row.style.display = match ? '' : 'none';
    });
    updateTargetCount();
}
function confirmCopy() {
    const fields = document.querySelectorAll('.fieldCb:checked').length;
    const targets = document.querySelectorAll('.targetCb:checked').length;
    if (fields === 0) { alert('Selecteer minimaal één veld om te kopiëren.'); return false; }
    if (targets === 0) { alert('Selecteer minimaal één doeласset.'); return false; }
    return confirm(`Weet je zeker dat je ${fields} veld(en) wilt kopiëren naar ${targets} asset(s)?`);
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
