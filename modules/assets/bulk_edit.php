<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');
requireLocation();

$errors   = [];
$success  = '';
$preview  = [];

// Alle bewerkbare velden met labels
$editableFields = [
    // Algemeen
    'brand'            => ['label' => 'Merk',                  'type' => 'text',   'group' => 'Algemeen'],
    'model'            => ['label' => 'Model',                 'type' => 'text',   'group' => 'Algemeen'],
    'type'             => ['label' => 'Soort',                 'type' => 'text',   'group' => 'Algemeen'],
    'room'             => ['label' => 'Ruimte',                'type' => 'select_room', 'group' => 'Algemeen'],
    'status'           => ['label' => 'Status',                'type' => 'select_status', 'group' => 'Algemeen'],
    'location_id'      => ['label' => 'Locatie',               'type' => 'select_location', 'group' => 'Algemeen'],
    'manufacturer_url' => ['label' => 'Fabrikant URL',         'type' => 'text',   'group' => 'Algemeen'],
    'business_critical'=> ['label' => 'Bedrijfskritisch',      'type' => 'select_bool', 'group' => 'Algemeen'],
    // Gebruik
    'assigned_to'      => ['label' => 'In gebruik bij',        'type' => 'text',   'group' => 'Gebruik'],
    'most_recent_user' => ['label' => 'Meest recente gebruiker','type' => 'text',  'group' => 'Gebruik'],
    'installed_date'   => ['label' => 'Geïnstalleerd op',      'type' => 'date',   'group' => 'Gebruik'],
    // Financieel
    'purchase_date'         => ['label' => 'Aankoopdatum',          'type' => 'date', 'group' => 'Financieel'],
    'warranty_end_date'     => ['label' => 'Einde garantie',        'type' => 'date', 'group' => 'Financieel'],
    'depreciation_years'    => ['label' => 'Afschrijving (jaren)',   'type' => 'number', 'group' => 'Financieel'],
    'advised_replacement_date' => ['label' => 'Advies vervangingsdatum', 'type' => 'date', 'group' => 'Financieel'],
    'autoupdate_expiry'     => ['label' => 'Autoupdate vervalt',    'type' => 'date', 'group' => 'Financieel'],
    // Netwerk
    'mac_address'      => ['label' => 'MAC-adres',             'type' => 'text',   'group' => 'Netwerk'],
    'lan_ip_address'   => ['label' => 'LAN IP-adres',          'type' => 'text',   'group' => 'Netwerk'],
    'management_ip'    => ['label' => 'Management IP',         'type' => 'text',   'group' => 'Netwerk'],
    'access_point_number' => ['label' => 'Access Point nr',   'type' => 'text',   'group' => 'Netwerk'],
    // Hardware
    'operating_system' => ['label' => 'Besturingssysteem',     'type' => 'text',   'group' => 'Hardware'],
    'ram'              => ['label' => 'RAM',                   'type' => 'text',   'group' => 'Hardware'],
    'cpu'              => ['label' => 'CPU',                   'type' => 'text',   'group' => 'Hardware'],
    'touchscreen_monitor_type' => ['label' => 'Monitor type',  'type' => 'text',   'group' => 'Hardware'],
    'monitor_count'    => ['label' => 'Aantal monitoren',      'type' => 'number', 'group' => 'Hardware'],
    'monitor_serial'   => ['label' => 'Serienummer monitor',   'type' => 'text',   'group' => 'Hardware'],
    'phone_number'     => ['label' => 'Telefoonnummer',        'type' => 'text',   'group' => 'Hardware'],
    // Overig
    'notes'            => ['label' => 'Opmerking',             'type' => 'textarea','group' => 'Overig'],
];

// Filter parameters
$filterBrand    = trim($_GET['filter_brand'] ?? '');
$filterType     = trim($_GET['filter_type'] ?? '');
$filterRoom     = trim($_GET['filter_room'] ?? '');
$filterStatus   = $_GET['filter_status'] ?? '';
$filterSearch   = trim($_GET['filter_search'] ?? '');
$locationId     = getLocationId();

// Bouw filter query
function buildFilterQuery(string $filterBrand, string $filterType, string $filterRoom, string $filterStatus, string $filterSearch, int $locationId): array {
    $where  = [];
    $params = [];

    if ($locationId) {
        $where[]  = "a.location_id = ?";
        $params[] = $locationId;
    }
    if ($filterBrand) {
        $where[]  = "a.brand LIKE ?";
        $params[] = "%$filterBrand%";
    }
    if ($filterType) {
        $where[]  = "a.type = ?";
        $params[] = $filterType;
    }
    if ($filterRoom) {
        $where[]  = "a.room = ?";
        $params[] = $filterRoom;
    }
    if ($filterStatus) {
        $where[]  = "a.status = ?";
        $params[] = $filterStatus;
    }
    if ($filterSearch) {
        $where[]  = "(a.asset_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)";
        $term     = "%$filterSearch%";
        $params   = array_merge($params, [$term, $term, $term]);
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return [$whereClause, $params];
}

// Verwerk bulk update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $assetIds   = array_map('intval', $_POST['asset_ids'] ?? []);
        $fieldName  = $_POST['field_name'] ?? '';
        $fieldValue = $_POST['field_value'] ?? '';
        $clearField = isset($_POST['clear_field']);

        if (empty($assetIds)) {
            $errors[] = 'Geen assets geselecteerd.';
        } elseif ($fieldName === 'bulk_image') {
            // Afbeelding bulk upload
            if (!isset($_FILES['bulk_image_file']) || $_FILES['bulk_image_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Geen afbeelding geüpload.';
            } else {
                $img = $_FILES['bulk_image_file'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $errors[] = 'Ongeldig afbeeldingstype.';
                } elseif ($img['size'] > 5 * 1024 * 1024) {
                    $errors[] = 'Afbeelding max 5MB.';
                } else {
                    $uploadDir = __DIR__ . '/../../assets/uploads/asset_images/';
                    $updatedCount = 0;
                    $overwrite = isset($_POST['overwrite_image']);
                    foreach ($assetIds as $aid) {
                        $existing = queryOne("SELECT id FROM asset_images WHERE asset_id = ? LIMIT 1", [$aid]);
                        if (!$existing || $overwrite) {
                            $newFilename = 'asset_' . $aid . '_' . time() . rand(10,99) . '.' . $ext;
                            if (copy($img['tmp_name'], $uploadDir . $newFilename)) {
                                execute(
                                    "INSERT INTO asset_images (asset_id, filename, original_name, sort_order, uploaded_at) VALUES (?, ?, ?, 1, NOW())",
                                    [$aid, $newFilename, $img['name']]
                                );
                                $updatedCount++;
                            }
                        }
                    }
                    $success = "$updatedCount asset(s) afbeelding toegevoegd.";
                }
            }
        } elseif (!array_key_exists($fieldName, $editableFields) && strpos($fieldName, 'custom_') !== 0) {
            $errors[] = 'Ongeldig veld geselecteerd.';
        } else {
            $updatedCount = 0;

            // Custom veld
            if (strpos($fieldName, 'custom_') === 0) {
                $cfName = substr($fieldName, 7);
                $cf = queryOne("SELECT id FROM custom_fields WHERE field_name = ? AND active = 1", [$cfName]);
                if ($cf) {
                    foreach ($assetIds as $aid) {
                        if ($clearField) {
                            execute("DELETE FROM custom_field_values WHERE asset_id = ? AND field_id = ?", [$aid, $cf['id']]);
                        } else {
                            execute("INSERT INTO custom_field_values (asset_id, field_id, value) VALUES (?, ?, ?)
                                     ON DUPLICATE KEY UPDATE value = ?",
                                [$aid, $cf['id'], $fieldValue, $fieldValue]);
                        }
                        $updatedCount++;
                    }
                }
            } else {
                // Standaard veld
                $value = $clearField ? null : ($fieldValue === '' ? null : $fieldValue);
                foreach ($assetIds as $aid) {
                    $old = getAssetById($aid);
                    execute("UPDATE assets SET $fieldName = ? WHERE id = ?", [$value, $aid]);
                    logAudit('BULK_UPDATE', 'assets', $aid, [$fieldName => $old[$fieldName] ?? null], [$fieldName => $value]);
                    $updatedCount++;
                }
                // Merk registreren
                if ($fieldName === 'brand' && !empty($fieldValue)) {
                    registerBrandUsage($fieldValue);
                }
            }

            $success = "$updatedCount asset(s) bijgewerkt — veld: " . ($editableFields[$fieldName]['label'] ?? $fieldName);
        }
    }
}

// Preview assets op basis van filter
$hasFilter = $filterBrand || $filterType || $filterRoom || $filterStatus || $filterSearch;
if ($hasFilter) {
    [$whereClause, $params] = buildFilterQuery($filterBrand, $filterType, $filterRoom, $filterStatus, $filterSearch, $locationId);
    $preview = query("SELECT a.id, a.asset_number, a.brand, a.model, a.type, a.room, a.status, l.name as location_name
                      FROM assets a
                      LEFT JOIN locations l ON a.location_id = l.id
                      $whereClause ORDER BY a.asset_number LIMIT 200", $params);
}

// Laad hulpdata
$allTypes    = getAssetTypes();
$allRooms    = getRoomsByLocation($locationId);
$allStatuses = getAssetStatuses();
$locations   = getUserLocations();
$customFields = query("SELECT field_name, field_label FROM custom_fields WHERE active = 1 ORDER BY sort_order");

$pageTitle = 'Bulk bewerken';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Bulk bewerken</h1>
    <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug naar assets</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- STAP 1: Filter -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
            Stap 1 — Filter assets
        </h3>
        <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
            <div class="form-group" style="margin:0;">
                <label>Zoeken</label>
                <input type="text" name="filter_search" class="form-control"
                       value="<?= htmlspecialchars($filterSearch) ?>"
                       placeholder="Nummer, merk, model...">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Merk</label>
                <input type="text" name="filter_brand" class="form-control"
                       value="<?= htmlspecialchars($filterBrand) ?>"
                       placeholder="bijv. HP, Dell, Acer">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Soort</label>
                <select name="filter_type" class="form-control">
                    <option value="">Alle soorten</option>
                    <?php foreach ($allTypes as $t): ?>
                    <option value="<?= htmlspecialchars($t['name']) ?>"
                        <?= $filterType === $t['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Ruimte</label>
                <select name="filter_room" class="form-control">
                    <option value="">Alle ruimtes</option>
                    <?php foreach ($allRooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['name']) ?>"
                        <?= $filterRoom === $r['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Status</label>
                <select name="filter_status" class="form-control">
                    <option value="">Alle statussen</option>
                    <?php foreach ($allStatuses as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $filterStatus === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">🔍 Filter toepassen</button>
                <a href="<?= BASE_URL ?>/modules/assets/bulk_edit.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($hasFilter): ?>
<!-- STAP 2: Resultaten + veld kiezen -->
<form method="POST" id="bulkForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="action" value="bulk_update">

    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
                <h3 style="margin:0;color:#1a2332;">
                    Stap 2 — Selecteer assets
                    <span style="font-weight:400;color:#6b7280;font-size:0.9rem;">
                        (<?= count($preview) ?> gevonden)
                    </span>
                </h3>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
                    <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                    Alles selecteren
                </label>
            </div>

            <?php if (empty($preview)): ?>
                <p style="color:#6b7280;text-align:center;padding:20px;">
                    Geen assets gevonden met deze filters.
                </p>
            <?php else: ?>
            <div style="max-height:350px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
                <table class="data-table" style="margin:0;">
                    <thead>
                        <tr>
                            <th width="40"></th>
                            <th>Assetnummer</th>
                            <th>Merk</th>
                            <th>Model</th>
                            <th>Soort</th>
                            <th>Ruimte</th>
                            <th>Status</th>
                            <th>Locatie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview as $a): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="asset_ids[]"
                                       value="<?= $a['id'] ?>"
                                       class="assetCb" checked>
                            </td>
                            <td><strong><?= htmlspecialchars($a['asset_number']) ?></strong></td>
                            <td><?= htmlspecialchars($a['brand'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['model'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['type'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['room'] ?? '') ?></td>
                            <td>
                                <?php $colors = ['In gebruik'=>'badge-success','Beschikbaar'=>'badge-info','In reparatie'=>'badge-warning','Buiten gebruik'=>'badge-danger','Afgevoerd'=>'badge-secondary']; ?>
                                <span class="badge <?= $colors[$a['status']] ?? 'badge-secondary' ?>">
                                    <?= htmlspecialchars($a['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($a['location_name'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:8px;color:#6b7280;font-size:0.85rem;">
                <span id="selectedCount"><?= count($preview) ?></span> asset(s) geselecteerd
            </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($preview)): ?>
    <!-- STAP 3: Veld en waarde kiezen -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                Stap 3 — Kies veld en nieuwe waarde
            </h3>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div class="form-group">
                    <label>Veld om te wijzigen</label>
                    <select name="field_name" id="fieldSelect" class="form-control" onchange="updateValueInput(this.value)">
                        <option value="">-- Kies een veld --</option>
                        <?php
                        $currentGroup = '';
                        foreach ($editableFields as $fname => $finfo):
                            if ($finfo['group'] !== $currentGroup):
                                if ($currentGroup) echo '</optgroup>';
                                $currentGroup = $finfo['group'];
                                echo '<optgroup label="' . htmlspecialchars($currentGroup) . '">';
                            endif;
                        ?>
                        <option value="<?= $fname ?>"><?= htmlspecialchars($finfo['label']) ?></option>
                        <?php endforeach; ?>
                        </optgroup>
                        <?php if (!empty($customFields)): ?>
                        <optgroup label="Custom velden">
                            <?php foreach ($customFields as $cf): ?>
                            <option value="custom_<?= htmlspecialchars($cf['field_name']) ?>">
                                <?= htmlspecialchars($cf['field_label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        <optgroup label="Afbeelding">
                            <option value="bulk_image">📷 Afbeelding toevoegen</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group" id="valueContainer">
                    <label>Nieuwe waarde</label>
                    <input type="text" name="field_value" id="fieldValue"
                           class="form-control" placeholder="Kies eerst een veld...">
                    <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;">
                        <input type="checkbox" name="clear_field" id="clearField"
                               onchange="toggleClear(this)">
                        <span style="font-size:0.875rem;color:#6b7280;">
                            Veld leegmaken (waarde verwijderen)
                        </span>
                    </label>
                </div>
                <div class="form-group" id="imageContainer" style="display:none;">
                    <label>Afbeelding uploaden</label>
                    <input type="file" name="bulk_image_file" id="bulkImageFile"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           class="form-control">
                    <small style="color:#6b7280;">Max 5MB. Wordt alleen toegevoegd bij assets zonder foto.</small>
                    <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;">
                        <input type="checkbox" name="overwrite_image" value="1">
                        <span style="font-size:0.875rem;color:#6b7280;">
                            Ook assets met bestaande foto overschrijven
                        </span>
                    </label>
                </div>
            </div>

            <div style="margin-top:15px;">
                <button type="submit" class="btn btn-primary"
                        onclick="return confirmBulk()"
                        style="padding:10px 24px;">
                    ✅ Wijziging doorvoeren
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</form>

<!-- Data voor JavaScript -->
<script>
const statusOptions = <?= json_encode(array_keys(getAssetStatuses())) ?>;
const locationOptions = <?= json_encode(array_map(fn($l) => ['id' => $l['id'], 'name' => $l['name']], getUserLocations())) ?>;
const roomOptions = <?= json_encode(array_map(fn($r) => $r['name'], getRoomsByLocation(getLocationId()))) ?>;

function toggleAll(cb) {
    document.querySelectorAll('.assetCb').forEach(c => c.checked = cb.checked);
    updateCount();
}

function updateCount() {
    const n = document.querySelectorAll('.assetCb:checked').length;
    document.getElementById('selectedCount').textContent = n;
}

document.querySelectorAll('.assetCb').forEach(c => {
    c.addEventListener('change', updateCount);
});

function toggleClear(cb) {
    document.getElementById('fieldValue').disabled = cb.checked;
    if (cb.checked) document.getElementById('fieldValue').value = '';
}

// Toon afbeelding upload veld als bulk_image geselecteerd
document.getElementById('fieldSelect').addEventListener('change', function() {
    const isImage = this.value === 'bulk_image';
    document.getElementById('imageContainer').style.display = isImage ? 'block' : 'none';
    document.getElementById('valueContainer').style.display = isImage ? 'none' : 'block';
});

function updateValueInput(fieldName) {
    const container = document.getElementById('valueContainer');
    const valueInput = document.getElementById('fieldValue');
    valueInput.disabled = false;
    document.getElementById('clearField').checked = false;

    // Verwijder bestaande select/textarea
    const existing = document.getElementById('dynamicInput');
    if (existing) existing.remove();

    if (fieldName === 'status') {
        const sel = document.createElement('select');
        sel.name = 'field_value';
        sel.id = 'dynamicInput';
        sel.className = 'form-control';
        sel.innerHTML = '<option value="">Kies status...</option>';
        statusOptions.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s; opt.textContent = s;
            sel.appendChild(opt);
        });
        valueInput.replaceWith(sel);

    } else if (fieldName === 'location_id') {
        const sel = document.createElement('select');
        sel.name = 'field_value';
        sel.id = 'dynamicInput';
        sel.className = 'form-control';
        sel.innerHTML = '<option value="">Kies locatie...</option>';
        locationOptions.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.id; opt.textContent = l.name;
            sel.appendChild(opt);
        });
        valueInput.replaceWith(sel);

    } else if (fieldName === 'room') {
        const sel = document.createElement('select');
        sel.name = 'field_value';
        sel.id = 'dynamicInput';
        sel.className = 'form-control';
        sel.innerHTML = '<option value="">Kies ruimte...</option>';
        roomOptions.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r; opt.textContent = r;
            sel.appendChild(opt);
        });
        valueInput.replaceWith(sel);

    } else if (fieldName === 'business_critical') {
        const sel = document.createElement('select');
        sel.name = 'field_value';
        sel.id = 'dynamicInput';
        sel.className = 'form-control';
        sel.innerHTML = '<option value="0">Nee</option><option value="1">Ja</option>';
        valueInput.replaceWith(sel);

    } else if (fieldName === 'notes') {
        const ta = document.createElement('textarea');
        ta.name = 'field_value';
        ta.id = 'dynamicInput';
        ta.className = 'form-control';
        ta.rows = 3;
        valueInput.replaceWith(ta);

    } else if (['purchase_date','warranty_end_date','installed_date',
                 'advised_replacement_date','autoupdate_expiry'].includes(fieldName)) {
        const inp = document.createElement('input');
        inp.type = 'date';
        inp.name = 'field_value';
        inp.id = 'dynamicInput';
        inp.className = 'form-control';
        valueInput.replaceWith(inp);

    } else if (['depreciation_years','monitor_count'].includes(fieldName)) {
        const inp = document.createElement('input');
        inp.type = 'number';
        inp.name = 'field_value';
        inp.id = 'dynamicInput';
        inp.className = 'form-control';
        inp.min = 0;
        valueInput.replaceWith(inp);

    } else {
        // Reset naar text input
        if (document.getElementById('fieldValue') === null) {
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.name = 'field_value';
            inp.id = 'fieldValue';
            inp.className = 'form-control';
            container.querySelector('label + *')?.replaceWith(inp) || 
            container.insertBefore(inp, container.querySelector('label:last-of-type'));
        }
    }
}

function confirmBulk() {
    const selected = document.querySelectorAll('.assetCb:checked').length;
    if (selected === 0) {
        alert('Selecteer minimaal één asset.');
        return false;
    }
    const field = document.getElementById('fieldSelect').value;
    if (!field) {
        alert('Kies een veld om te wijzigen.');
        return false;
    }
    const clearing = document.getElementById('clearField').checked;
    const msg = clearing
        ? `Weet je zeker dat je het veld wilt leegmaken voor ${selected} asset(s)?`
        : `Weet je zeker dat je dit veld wilt wijzigen voor ${selected} asset(s)?`;
    return confirm(msg);
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
