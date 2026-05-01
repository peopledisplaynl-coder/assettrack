<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('print_labels');

// Filters
$search       = trim($_GET['search']        ?? '');
$filterStatus = $_GET['filter_status'] ?? '';
$filterType   = $_GET['filter_type']   ?? '';
$filterRoom   = $_GET['filter_room']   ?? '';
$locationId   = getLocationId();

$sql    = "SELECT id, asset_number, brand, model, room, status, serial_number, lan_ip_address, type FROM assets WHERE 1=1";
$params = [];
if ($locationId)   { $sql .= " AND location_id = ?"; $params[] = $locationId; }
if ($search)       { $sql .= " AND (asset_number LIKE ? OR brand LIKE ? OR model LIKE ? OR room LIKE ?)"; $params = array_merge($params, array_fill(0, 4, "%$search%")); }
if ($filterStatus) { $sql .= " AND status = ?"; $params[] = $filterStatus; }
if ($filterType)   { $sql .= " AND type = ?";   $params[] = $filterType; }
if ($filterRoom)   { $sql .= " AND room = ?";   $params[] = $filterRoom; }
$sql .= " ORDER BY asset_number";
$assets = query($sql, $params);

$allTypes    = getAssetTypes();
$allStatuses = getAssetStatuses();
$allRooms    = getRoomsByLocation($locationId);

// Formaat definities
$labelFormats = [
    '— Losse labels (labelprinter) —' => [
        'small'         => 'Klein (38×25mm) — Brother / generiek',
        'medium'        => 'Middel (62×29mm) — Brother QL standaard',
        'large'         => 'Groot (89×36mm) — Zebra / generiek',
        'dymo_small'    => 'Dymo 11354 (57×32mm)',
        'dymo_medium'   => 'Dymo 99010 (89×28mm)',
        'brother_small' => 'Brother DK-11201 (29×62mm)',
        'zebra_50x25'   => 'Zebra (50×25mm)',
        'custom'        => '⚙️ Aangepast formaat...',
    ],
    '— A4 labelvel — Avery Zweckform —' => [
        'avery_l7160' => 'Avery L7160 — 21 labels (63.5×38.1mm)',
        'avery_l7159' => 'Avery L7159 — 24 labels (63.5×33.9mm)',
        'avery_l7162' => 'Avery L7162 — 16 labels (99.1×33.9mm)',
        'avery_l7163' => 'Avery L7163 — 14 labels (99.1×38.1mm)',
        'avery_l7165' => 'Avery L7165 — 8 labels (99.1×67.7mm)',
        'avery_l7166' => 'Avery L7166 — 6 labels (99.1×93.1mm)',
        'avery_l7173' => 'Avery L7173 — 40 labels (48.5×25.4mm)',
        'avery_l7636' => 'Avery L7636 — 36 labels (48.9×29.6mm)',
    ],
    '— A4 labelvel — Hema / generiek —' => [
        'a4_10'  => 'A4 — 10 labels per vel (99×57mm)',
        'a4_21'  => 'A4 — 21 labels per vel (70×42mm)',
        'a4_24'  => 'A4 — 24 labels per vel (66×34mm)',
        'a4_40'  => 'A4 — 40 labels per vel (48×25mm)',
        'a4_65'  => 'A4 — 65 labels per vel (38×21mm)',
    ],
];

// Max extra velden per formaat
$maxFields = [
    'small'         => 2,
    'medium'        => 4,
    'large'         => 6,
    'dymo_small'    => 4,
    'dymo_medium'   => 3,
    'brother_small' => 5,
    'zebra_50x25'   => 2,
    'custom'        => 6,
    'avery_l7160'   => 7,
    'avery_l7159'   => 6,
    'avery_l7162'   => 5,
    'avery_l7163'   => 6,
    'avery_l7165'   => 8,
    'avery_l7166'   => 9,
    'avery_l7173'   => 4,
    'avery_l7636'   => 5,
    'a4_10'         => 9,
    'a4_21'         => 7,
    'a4_24'         => 6,
    'a4_40'         => 4,
    'a4_65'         => 3,
];

$pageTitle = 'Labels afdrukken';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Labels afdrukken</h1>
</div>

<!-- Filter -->
<form method="GET" action="<?= BASE_URL ?>/modules/labels/" style="margin-bottom:15px;">
    <div class="card">
        <div class="card-body">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:2px;">Zoeken</label>
                    <input type="text" name="search" class="form-control" style="width:180px;"
                           placeholder="Nummer, merk, model..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:2px;">Status</label>
                    <select name="filter_status" class="form-control" style="width:150px;">
                        <option value="">Alle statussen</option>
                        <?php foreach ($allStatuses as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $filterStatus===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:2px;">Soort</label>
                    <select name="filter_type" class="form-control" style="width:140px;">
                        <option value="">Alle soorten</option>
                        <?php foreach ($allTypes as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>" <?= $filterType===$t['name']?'selected':'' ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:2px;">Ruimte</label>
                    <select name="filter_room" class="form-control" style="width:150px;">
                        <option value="">Alle ruimtes</option>
                        <?php foreach ($allRooms as $r): ?>
                        <option value="<?= htmlspecialchars($r['name']) ?>" <?= $filterRoom===$r['name']?'selected':'' ?>>
                            <?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:6px;">
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="<?= BASE_URL ?>/modules/labels/" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Hoofdformulier -->
<form method="POST" action="<?= BASE_URL ?>/modules/labels/print.php" id="labelForm">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

        <!-- Asset selectie -->
        <div class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;color:#1a2332;">Stap 1 — Selecteer assets</h3>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.875rem;">
                        <input type="checkbox" id="selectAll" onchange="toggleAll(this)"> Alles selecteren
                    </label>
                </div>
                <div style="max-height:500px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;">
                    <table class="data-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th width="36"></th>
                                <th>Assetnummer</th>
                                <th>Merk / Model</th>
                                <th>Ruimte</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assets as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="asset_ids[]" value="<?= $a['id'] ?>" class="asset-cb" onchange="updateCount()"></td>
                            <td><?= htmlspecialchars($a['asset_number']) ?></td>
                            <td><?= htmlspecialchars(trim(($a['brand']??'').' '.($a['model']??''))) ?></td>
                            <td><?= htmlspecialchars($a['room'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['status'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top:8px;color:#6b7280;font-size:0.85rem;">
                    <span id="selectedCount">0</span> van <?= count($assets) ?> geselecteerd
                </p>
            </div>
        </div>

        <!-- Opties rechts -->
        <div>
            <!-- Stap 2: Formaat -->
            <div class="card" style="margin-bottom:15px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:8px;">
                        Stap 2 — Formaat kiezen
                    </h3>
                    <?php foreach ($labelFormats as $groupLabel => $groupFormats): ?>
                    <div style="font-size:0.75rem;color:#6b7280;font-weight:600;margin:10px 0 6px;">
                        <?= $groupLabel ?>
                    </div>
                    <?php foreach ($groupFormats as $val => $lbl): ?>
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:5px;cursor:pointer;">
                        <input type="radio" name="format" value="<?= $val ?>"
                               <?= $val === 'medium' ? 'checked' : '' ?>
                               onchange="updateFormatUI('<?= $val ?>')">
                        <span style="font-size:0.875rem;"><?= $lbl ?></span>
                    </label>
                    <?php endforeach; ?>
                    <?php endforeach; ?>

                    <!-- Aangepast formaat velden -->
                    <div id="customSizeFields" style="display:none;margin-top:10px;padding:10px;
                         background:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
                        <div style="display:flex;gap:10px;align-items:flex-end;">
                            <div class="form-group" style="margin:0;flex:1;">
                                <label style="font-size:0.8rem;">Breedte (mm)</label>
                                <input type="number" name="custom_w" id="custom_w"
                                       class="form-control" value="62" min="20" max="200">
                            </div>
                            <div class="form-group" style="margin:0;flex:1;">
                                <label style="font-size:0.8rem;">Hoogte (mm)</label>
                                <input type="number" name="custom_h" id="custom_h"
                                       class="form-control" value="29" min="15" max="200">
                            </div>
                        </div>
                        <p style="font-size:0.75rem;color:#6b7280;margin-top:6px;">
                            Voer de exacte afmeting in van je label in millimeters.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stap 3: Velden -->
            <div class="card" style="margin-bottom:15px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:8px;">
                        Stap 3 — Velden op label
                    </h3>
                    <p style="font-size:0.78rem;color:#6b7280;margin-bottom:10px;" id="maxFieldsHint">
                        Kies het formaat om te zien hoeveel velden passen.
                    </p>

                    <!-- Verplichte velden -->
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:5px;opacity:0.6;">
                        <input type="checkbox" checked disabled> Assetnummer <small>(altijd)</small>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;opacity:0.6;">
                        <input type="checkbox" checked disabled> QR-code <small>(altijd)</small>
                    </label>
                    <input type="hidden" name="show_asset_number" value="1">
                    <input type="hidden" name="show_qr" value="1">

                    <!-- Optionele velden -->
                    <?php
                    $optionalFields = [
                        'show_brand_model' => 'Merk + Model',
                        'show_company'     => 'Organisatienaam',
                        'show_location'    => 'Locatienaam',
                        'show_serial'      => 'Serienummer',
                        'show_room'        => 'Ruimte',
                        'show_status'      => 'Status',
                        'show_ip'          => 'IP-adres',
                    ];
                    ?>
                    <?php foreach ($optionalFields as $fname => $flabel): ?>
                    <label class="field-option" style="display:flex;align-items:center;gap:8px;
                           margin-bottom:5px;padding:5px 8px;border-radius:5px;
                           border:1px solid #e5e7eb;cursor:pointer;">
                        <input type="checkbox" name="<?= $fname ?>" value="1"
                               class="field-cb" <?= $fname==='show_brand_model'?'checked':'' ?>>
                        <span style="font-size:0.875rem;"><?= $flabel ?></span>
                    </label>
                    <?php endforeach; ?>

                    <div id="fieldWarning" style="display:none;margin-top:8px;padding:7px 10px;
                         background:#fef3c7;border-radius:5px;font-size:0.78rem;color:#92400e;">
                    </div>
                </div>
            </div>

            <!-- Genereer knop -->
            <button type="submit" class="btn btn-primary"
                    style="width:100%;padding:12px;font-size:1rem;"
                    onclick="return checkSelection()">
                🖨️ Labels genereren
            </button>
        </div>
    </div>
</form>

<script>
const maxFieldsMap = <?= json_encode($maxFields) ?>;

function toggleAll(cb) {
    document.querySelectorAll('.asset-cb').forEach(c => c.checked = cb.checked);
    updateCount();
}
function updateCount() {
    const n = document.querySelectorAll('.asset-cb:checked').length;
    document.getElementById('selectedCount').textContent = n;
    document.getElementById('selectAll').checked =
        n === document.querySelectorAll('.asset-cb').length && n > 0;
}
function checkSelection() {
    if (!document.querySelectorAll('.asset-cb:checked').length) {
        alert('Selecteer minimaal één asset.');
        return false;
    }
    return true;
}

function updateFormatUI(format) {
    // Toon/verberg aangepast formaat velden
    document.getElementById('customSizeFields').style.display =
        format === 'custom' ? 'block' : 'none';

    // Update veld limiet
    const max = maxFieldsMap[format] || 4;
    const cbs = document.querySelectorAll('.field-cb');
    const hint = document.getElementById('maxFieldsHint');
    hint.textContent = 'Max ' + max + ' extra veld(en) voor dit formaat.';

    let checked = Array.from(cbs).filter(cb => cb.checked).length;
    cbs.forEach(cb => {
        const label = cb.closest('label');
        if (!cb.checked && checked >= max) {
            cb.disabled = true;
            label.style.opacity = '0.4';
        } else {
            cb.disabled = false;
            label.style.opacity = '1';
        }
    });

    const warning = document.getElementById('fieldWarning');
    if (checked > max) {
        warning.style.display = 'block';
        warning.textContent = '⚠ ' + checked + ' velden geselecteerd maar max is ' + max + ' voor dit formaat.';
    } else {
        warning.style.display = 'none';
    }
}

document.querySelectorAll('.field-cb').forEach(cb => {
    cb.addEventListener('change', () => {
        const format = document.querySelector('input[name="format"]:checked')?.value || 'medium';
        updateFormatUI(format);
    });
});

// Init
updateFormatUI('medium');
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
