<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');
requireLocation();

$templateId = (int)($_GET['template_id'] ?? $_POST['template_id'] ?? 0);
$template   = $templateId ? queryOne("SELECT * FROM asset_templates WHERE id = ? AND active = 1", [$templateId]) : null;

if (!$template) {
    header('Location: ' . BASE_URL . '/modules/assets/templates.php?success=Template+niet+gevonden');
    exit;
}

$errors  = [];
$success = '';
$locationId = getLocationId();

// Velden die gesynchroniseerd kunnen worden
$syncFields = [
    'brand'                    => 'Merk',
    'asset_type'               => 'Soort',
    'depreciation_years'       => 'Afschrijving (jaren)',
    'manufacturer_url'         => 'Fabrikant URL',
    'operating_system'         => 'Besturingssysteem',
    'ram'                      => 'RAM',
    'cpu'                      => 'CPU',
    'business_critical'        => 'Bedrijfskritisch',
    'touchscreen_monitor_type' => 'Monitor type',
    'monitor_count'            => 'Aantal monitoren',
    'notes'                    => 'Standaard notitie',
];

// Afbeelding apart behandelen
$templateHasImage = !empty($template['image_filename']) &&
    file_exists(__DIR__ . '/../../assets/uploads/template_images/' . $template['image_filename']);

// Mapping template veld → asset veld
$fieldMap = [
    'asset_type' => 'type',
];

// Verwerk synchronisatie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $assetIds     = array_map('intval', $_POST['asset_ids'] ?? []);
        $syncSelected = $_POST['sync_fields'] ?? [];

        if (empty($assetIds)) {
            $errors[] = 'Geen assets geselecteerd.';
        } elseif (empty($syncSelected)) {
            $errors[] = 'Selecteer minimaal één veld om te synchroniseren.';
        } else {
            $updatedCount = 0;
            foreach ($assetIds as $aid) {
                $updateData = [];
                foreach ($syncSelected as $tf) {
                    if (!array_key_exists($tf, $syncFields)) continue;
                    $assetField = $fieldMap[$tf] ?? $tf;
                    $val = $template[$tf] ?? null;
                    if ($val !== null && $val !== '') {
                        $updateData[$assetField] = $val;
                    }
                }

                // Speciale behandeling: warranty_months → bereken warranty_end_date
                if (in_array('warranty_months', $syncSelected) && !empty($template['warranty_months'])) {
                    $asset = getAssetById($aid);
                    if (!empty($asset['purchase_date'])) {
                        $d = new DateTime($asset['purchase_date']);
                        $d->add(new DateInterval('P' . (int)$template['warranty_months'] . 'M'));
                        $updateData['warranty_end_date'] = $d->format('Y-m-d');
                    }
                }

                if (!empty($updateData)) {
                    $old = getAssetById($aid);
                    updateAsset($aid, $updateData);
                    logAudit('TEMPLATE_SYNC', 'assets', $aid,
                        array_intersect_key($old, array_flip(array_keys($updateData))),
                        $updateData
                    );
                    $updatedCount++;
                } elseif (in_array('copy_image', $syncSelected)) {
                    $updatedCount++; // tellen ook als alleen afbeelding gekopieerd wordt
                }

                // Afbeelding kopiëren van template naar asset
                if (in_array('copy_image', $syncSelected) && $templateHasImage) {
                    $srcPath = __DIR__ . '/../../assets/uploads/template_images/' . $template['image_filename'];
                    $ext = pathinfo($template['image_filename'], PATHINFO_EXTENSION);
                    $newFilename = 'asset_' . $aid . '_' . time() . rand(10,99) . '.' . $ext;
                    $dstPath = __DIR__ . '/../../assets/uploads/asset_images/' . $newFilename;

                    // Kopieer alleen als asset nog geen foto heeft
                    $existing = queryOne("SELECT id FROM asset_images WHERE asset_id = ? LIMIT 1", [$aid]);
                    if (!$existing && copy($srcPath, $dstPath)) {
                        execute(
                            "INSERT INTO asset_images (asset_id, filename, original_name, sort_order, uploaded_at) VALUES (?, ?, ?, 1, NOW())",
                            [$aid, $newFilename, $template['image_filename']]
                        );
                    }
                }
            }
            $success = "$updatedCount asset(s) gesynchroniseerd met template '{$template['name']}'";
        }
    }
}

// Laad assets — filter op type OF merk (niet verplicht beide)
$filterSearch = trim($_GET['asset_search'] ?? '');
// Alleen filteren als gebruiker dit expliciet instelt via GET
// Standaard: toon ALLE assets van de actieve locatie
$filterType   = array_key_exists('filter_type', $_GET) ? $_GET['filter_type'] : '';
$filterBrand  = array_key_exists('filter_brand', $_GET) ? $_GET['filter_brand'] : '';

$where  = [];
$params = [];

if ($locationId) {
    $where[]  = "a.location_id = ?";
    $params[] = $locationId;
}

// Type filter — alleen als ingesteld
if ($filterType) {
    $where[]  = "a.type = ?";
    $params[] = $filterType;
}

// Merk filter — alleen als gebruiker het zelf instelt
if ($filterBrand) {
    $where[]  = "a.brand LIKE ?";
    $params[] = '%' . $filterBrand . '%';
}

// Zoekterm
if ($filterSearch) {
    $where[]  = "(a.asset_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)";
    $term     = '%' . $filterSearch . '%';
    $params   = array_merge($params, [$term, $term, $term]);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$matchingAssets = query(
    "SELECT a.id, a.asset_number, a.brand, a.model, a.type, a.room, a.status, l.name as location_name
     FROM assets a LEFT JOIN locations l ON a.location_id = l.id
     $whereClause ORDER BY a.asset_number LIMIT 300",
    $params
);

$pageTitle = 'Template synchroniseren';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>
        <span style="font-size:1.5rem;"><?= htmlspecialchars($template['icon'] ?? '📦') ?></span>
        Synchroniseer: <?= htmlspecialchars($template['name']) ?>
    </h1>
    <a href="<?= BASE_URL ?>/modules/assets/templates.php" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Template info -->
<div class="card" style="margin-bottom:20px;background:#eff6ff;border:1px solid #bfdbfe;">
    <div class="card-body">
        <strong>Template waarden:</strong>
        <div style="display:flex;flex-wrap:wrap;gap:15px;margin-top:8px;font-size:0.875rem;">
            <?php foreach ($syncFields as $tf => $tlabel): ?>
            <?php if (!empty($template[$tf])): ?>
            <span>
                <span style="color:#6b7280;"><?= $tlabel ?>:</span>
                <strong><?= htmlspecialchars((string)$template[$tf]) ?></strong>
            </span>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!empty($template['warranty_months'])): ?>
            <span>
                <span style="color:#6b7280;">Garantie:</span>
                <strong><?= $template['warranty_months'] ?> maanden</strong>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="action" value="sync">
    <input type="hidden" name="template_id" value="<?= $templateId ?>">

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">

        <!-- Velden kiezen -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    Stap 1 — Kies velden
                </h3>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;
                               cursor:pointer;font-weight:600;color:#2563eb;">
                    <input type="checkbox" onchange="toggleAllSync(this)">
                    Alles selecteren
                </label>
                <div style="display:grid;gap:6px;">
                    <?php foreach ($syncFields as $tf => $tlabel): ?>
                    <?php $val = $template[$tf] ?? null; ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                  padding:7px;border-radius:6px;border:1px solid #e5e7eb;
                                  <?= empty($val) ? 'opacity:0.5;' : '' ?>">
                        <input type="checkbox" name="sync_fields[]"
                               value="<?= $tf ?>" class="syncCb"
                               <?= empty($val) ? 'disabled' : '' ?>>
                        <span style="flex:1;">
                            <strong style="font-size:0.8rem;"><?= htmlspecialchars($tlabel) ?></strong>
                            <span style="display:block;font-size:0.75rem;color:#6b7280;">
                                <?= !empty($val) ? htmlspecialchars(mb_strimwidth((string)$val, 0, 40, '...')) : 'Niet ingesteld in template' ?>
                            </span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                    <?php if (!empty($template['warranty_months'])): ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                  padding:7px;border-radius:6px;border:1px solid #e5e7eb;">
                        <input type="checkbox" name="sync_fields[]" value="warranty_months" class="syncCb">
                        <span style="flex:1;">
                            <strong style="font-size:0.8rem;">Garantiedatum berekenen</strong>
                            <span style="display:block;font-size:0.75rem;color:#6b7280;">
                                Aankoopdatum + <?= $template['warranty_months'] ?> maanden
                            </span>
                        </span>
                    </label>
                    <?php endif; ?>
                    <?php if ($templateHasImage): ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                  padding:7px;border-radius:6px;border:2px solid #2563eb;background:#eff6ff;">
                        <input type="checkbox" name="sync_fields[]" value="copy_image" class="syncCb">
                        <span style="flex:1;">
                            <strong style="font-size:0.8rem;">📷 Standaard afbeelding kopiëren</strong>
                            <span style="display:block;font-size:0.75rem;color:#6b7280;">
                                Alleen voor assets zonder eigen foto
                            </span>
                            <img src="<?= BASE_URL ?>/assets/uploads/template_images/<?= htmlspecialchars($template['image_filename']) ?>"
                                 style="margin-top:6px;max-width:100%;max-height:60px;border-radius:6px;object-fit:cover;">
                        </span>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Assets kiezen -->
        <div class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;color:#1a2332;">
                        Stap 2 — Kies assets
                        <span style="font-weight:400;color:#6b7280;font-size:0.875rem;">
                            (<?= count($matchingAssets) ?> gevonden)
                        </span>
                    </h3>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.875rem;">
                        <input type="checkbox" id="selectAllAssets" onchange="toggleAllAssets(this)">
                        Alles selecteren
                    </label>
                </div>

                <!-- Filter assets -->
                <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                    <input type="text"
                           class="form-control" style="flex:1;min-width:140px;"
                           placeholder="Zoek op nummer, merk, model..."
                           oninput="filterSyncAssets(this.value)"
                           value="<?= htmlspecialchars($filterSearch) ?>">
                    <select class="form-control" style="width:160px;"
                            onchange="applyTypeFilter(this.value)">
                        <option value="">Alle soorten</option>
                        <?php foreach (getAssetTypes() as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>"
                            <?= $filterType === $t['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="?template_id=<?= $templateId ?>" 
                       class="btn btn-secondary" style="white-space:nowrap;">
                        Alles tonen
                    </a>
                </div>

                <div style="max-height:420px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
                    <?php if (empty($matchingAssets)): ?>
                    <p style="padding:20px;color:#6b7280;text-align:center;">
                        Geen assets gevonden die overeenkomen met dit template.<br>
                        <small>Filter: type=<?= htmlspecialchars($template['asset_type'] ?? 'alle') ?>, merk=<?= htmlspecialchars($template['brand'] ?? 'alle') ?></small>
                    </p>
                    <?php else: ?>
                    <table class="data-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th width="36"></th>
                                <th>Assetnummer</th>
                                <th>Merk/Model</th>
                                <th>Ruimte</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="syncAssetList">
                            <?php foreach ($matchingAssets as $a): ?>
                            <tr class="sync-row"
                                data-search="<?= strtolower(htmlspecialchars($a['asset_number'] . ' ' . $a['brand'] . ' ' . $a['model'])) ?>">
                                <td>
                                    <input type="checkbox" name="asset_ids[]"
                                           value="<?= $a['id'] ?>"
                                           class="assetSyncCb" checked>
                                </td>
                                <td><strong style="font-size:0.8rem;"><?= htmlspecialchars($a['asset_number']) ?></strong></td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars(trim(($a['brand'] ?? '') . ' ' . ($a['model'] ?? ''))) ?></td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars($a['room'] ?? '') ?></td>
                                <td>
                                    <?php $colors = ['In gebruik'=>'badge-success','Beschikbaar'=>'badge-info','In reparatie'=>'badge-warning','Buiten gebruik'=>'badge-danger','Afgevoerd'=>'badge-secondary']; ?>
                                    <span class="badge <?= $colors[$a['status']] ?? 'badge-secondary' ?>" style="font-size:0.7rem;">
                                        <?= htmlspecialchars($a['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <p style="margin-top:8px;color:#6b7280;font-size:0.85rem;">
                    <span id="syncSelectedCount"><?= count($matchingAssets) ?></span> geselecteerd
                </p>
            </div>
        </div>
    </div>

    <?php if (!empty($matchingAssets)): ?>
    <div style="margin-top:20px;text-align:right;">
        <button type="submit" class="btn btn-primary"
                onclick="return confirmSync()"
                style="padding:12px 28px;font-size:1rem;">
            🔄 Synchroniseren
        </button>
    </div>
    <?php endif; ?>
</form>

<script>
function toggleAllSync(cb) {
    document.querySelectorAll('.syncCb:not(:disabled)').forEach(c => c.checked = cb.checked);
}
function toggleAllAssets(cb) {
    document.querySelectorAll('.assetSyncCb').forEach(c => {
        if (c.closest('tr').style.display !== 'none') c.checked = cb.checked;
    });
    updateSyncCount();
}
function updateSyncCount() {
    const n = document.querySelectorAll('.assetSyncCb:checked').length;
    document.getElementById('syncSelectedCount').textContent = n;
}
document.querySelectorAll('.assetSyncCb').forEach(c => {
    c.addEventListener('change', updateSyncCount);
});
function filterSyncAssets(val) {
    val = val.toLowerCase();
    document.querySelectorAll('.sync-row').forEach(row => {
        row.style.display = row.dataset.search.includes(val) ? '' : 'none';
    });
}
function applyTypeFilter(type) {
    const url = new URL(window.location.href);
    if (type) url.searchParams.set('filter_type', type);
    else url.searchParams.delete('filter_type');
    window.location.href = url.toString();
}
function confirmSync() {
    const fields  = document.querySelectorAll('.syncCb:checked').length;
    const assets  = document.querySelectorAll('.assetSyncCb:checked').length;
    if (fields === 0) { alert('Selecteer minimaal één veld.'); return false; }
    if (assets === 0) { alert('Selecteer minimaal één asset.'); return false; }
    return confirm(`Weet je zeker dat je ${fields} veld(en) wilt synchroniseren naar ${assets} asset(s)?\n\nBestaande waarden worden overschreven.`);
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
