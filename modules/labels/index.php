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
        'dymo_99012'    => 'Dymo 99012 / 99017 (36×89mm rol, automatisch gedraaid)',
        'dymo_11355'    => 'Dymo 11355 (19×51mm rol, automatisch gedraaid) — minimalistisch: alleen assetnummer + code',
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
    // Op verzoek van Ton (2026-09-19): een Fichero Mini Pocket Printer type 5836,
    // een Bluetooth-labelprinter die alleen via zijn eigen telefoon-app bediend
    // kan worden (geen officiële koppel-API). Dit "formaat" genereert daarom geen
    // printbare pagina zoals de andere formaten, maar losse PNG-afbeeldingen die
    // je zelf in de Fichero-app importeert -- zie modules/labels/export_image.php.
    '— Bluetooth pocket-printer (losse PNG-afbeelding) —' => [
        'fichero_5836' => 'Fichero Mini Pocket Printer 5836 (30×14mm) — genereert een PNG per label',
    ],
];

// Max extra velden per formaat
$maxFields = [
    'small'         => 2,
    'medium'        => 4,
    'large'         => 6,
    'dymo_small'    => 4,
    'dymo_medium'   => 3,
    'dymo_99012'    => 6,
    // Dymo 11355 is bewust het "minimalistische" formaat: alleen assetnummer +
    // code (QR of streepjescode), geen ruimte voor extra velden.
    'dymo_11355'    => 0,
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
    // Bewust minimalistisch, net als de Dymo 11355 hierboven -- 30x14mm is
    // fysiek te klein voor meer dan assetnummer + code.
    'fichero_5836'  => 0,
];

// Formaten voor losse labelprinters (rol-labels, bv. Dymo/Brother/Zebra) --
// alleen voor deze formaten is de afdrukrichting (90° rotatie) relevant.
// A4-vellen liggen altijd vast in de printer, dus daar is dit niet nodig.
$looseFormats = array_keys($labelFormats['— Losse labels (labelprinter) —']);

// Formaten die altijd automatisch gedraaid worden (rollen waarvan de fysieke
// breedte smaller is dan de leesbare inhoud, zoals Dymo 99012 en Dymo 11355)
// -- daarvoor is het handmatige vinkje overbodig/verwarrend, dus die tonen we
// niet.
$autoRotateFormats = ['dymo_99012', 'dymo_11355'];
$rotatableFormats  = array_values(array_diff($looseFormats, $autoRotateFormats));

// Formaten die geen printbare pagina opleveren maar losse PNG-afbeeldingen
// (zie hierboven bij $labelFormats) -- het hoofdformulier post hiervoor naar
// een andere endpoint, geregeld via JS onderin deze pagina.
$imageOnlyFormats = ['fichero_5836'];

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

                    <!-- Afdrukrichting (alleen relevant voor losse labelprinters) -->
                    <div id="rotateField" style="display:none;margin-top:10px;padding:10px;
                         background:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
                        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="rotate_label" value="1" style="margin-top:2px;">
                            <span style="font-size:0.875rem;">
                                Label 90° gedraaid afdrukken
                                <br><small style="color:#6b7280;">
                                    Gebruik dit als het label nu verkeerd om (op zijn kant) uit de printer komt.
                                </small>
                            </span>
                        </label>
                    </div>
                    <div id="autoRotateNote" style="display:none;margin-top:10px;padding:10px;
                         background:#eff6ff;border-radius:6px;border:1px solid #bfdbfe;
                         font-size:0.8rem;color:#1e3a8a;">
                        Dit label wordt automatisch gedraaid zodat het op de rol past — een handmatig vinkje is hier niet nodig.
                    </div>
                    <div id="ficheroImageNote" style="display:none;margin-top:10px;padding:10px;
                         background:#eff6ff;border-radius:6px;border:1px solid #bfdbfe;
                         font-size:0.8rem;color:#1e3a8a;">
                        Dit formaat genereert losse PNG-afbeeldingen (geen printdialoog) — sla elke afbeelding
                        op en importeer 'm in de Fichero-app om te printen.
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
                    <input type="hidden" name="show_asset_number" value="1">
                    <input type="hidden" name="show_qr" value="1">

                    <!-- Keuze QR-code of streepjescode -->
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">
                            Code op label
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;cursor:pointer;">
                            <input type="radio" name="code_type" value="qr" checked>
                            <span style="font-size:0.875rem;">QR-code
                                <small style="color:#6b7280;">(scannen met telefoon)</small></span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="radio" name="code_type" value="barcode">
                            <span style="font-size:0.875rem;">Streepjescode
                                <small style="color:#6b7280;">(Code128, voor oudere pc-scanners)</small></span>
                        </label>
                    </div>

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
            <button type="submit" class="btn btn-primary" id="labelSubmitBtn"
                    style="width:100%;padding:12px;font-size:1rem;"
                    onclick="return checkSelection()">
                🖨️ Labels genereren
            </button>
        </div>
    </div>
</form>

<script>
const maxFieldsMap = <?= json_encode($maxFields) ?>;
const rotatableFormats = <?= json_encode($rotatableFormats) ?>;
const autoRotateFormats = <?= json_encode($autoRotateFormats) ?>;
const imageFormats = <?= json_encode($imageOnlyFormats) ?>;

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

    // Afdrukrichting alleen tonen bij losse labelprinters die dit niet al
    // automatisch doen (A4-vellen nooit, Dymo 99012 altijd -- die is te smal
    // voor de content en wordt sowieso gedraaid, het vinkje zou daar overbodig
    // en verwarrend zijn).
    document.getElementById('rotateField').style.display =
        rotatableFormats.includes(format) ? 'block' : 'none';
    document.getElementById('autoRotateNote').style.display =
        autoRotateFormats.includes(format) ? 'block' : 'none';
    document.getElementById('ficheroImageNote').style.display =
        imageFormats.includes(format) ? 'block' : 'none';

    // Dit formaat genereert losse PNG-afbeeldingen i.p.v. een printbare pagina
    // -- het formulier post dus naar een andere endpoint (zie
    // modules/labels/export_image.php), en de knoptekst past zich mee aan.
    const form = document.getElementById('labelForm');
    const submitBtn = document.getElementById('labelSubmitBtn');
    if (imageFormats.includes(format)) {
        form.action = '<?= BASE_URL ?>/modules/labels/export_image.php';
        if (submitBtn) submitBtn.textContent = '📱 PNG-afbeeldingen genereren';
    } else {
        form.action = '<?= BASE_URL ?>/modules/labels/print.php';
        if (submitBtn) submitBtn.textContent = '🖨️ Labels genereren';
    }

    // Update veld limiet. Let op: "?? 4" i.p.v. "|| 4" -- een formaat met max 0
    // (zoals de minimalistische Dymo 11355) is anders een valse 0, en JS's "||"
    // zou die ten onrechte vervangen door de standaardwaarde 4.
    const max = maxFieldsMap[format] ?? 4;
    const cbs = document.querySelectorAll('.field-cb');
    const hint = document.getElementById('maxFieldsHint');
    hint.textContent = max === 0
        ? 'Dit formaat is bewust minimalistisch: alleen assetnummer + code, geen ruimte voor extra velden.'
        : 'Max ' + max + ' extra veld(en) voor dit formaat.';

    // Als er (bv. door een eerder formaat, of onthouden instellingen) meer velden
    // aangevinkt staan dan deze nieuwe limiet toelaat, zet de teveel aangevinkte
    // velden automatisch uit i.p.v. alleen te waarschuwen -- anders blijft een
    // "minimalistisch" formaat zoals de 11355 niet echt minimaal.
    const checkedCbs = Array.from(cbs).filter(cb => cb.checked);
    if (checkedCbs.length > max) {
        checkedCbs.slice(max).forEach(cb => { cb.checked = false; });
    }

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

// Onthoud de laatst gebruikte labelinstellingen in de browser (localStorage),
// zodat je bij een volgende printopdracht niet steeds opnieuw formaat, code-type
// en velden hoeft in te stellen. Dit is per browser/apparaat, niet gekoppeld aan
// het gebruikersaccount -- alles blijft lokaal, er wordt niets naar de server
// gestuurd of in de database opgeslagen.
const LABEL_PREFS_KEY = 'assettrack_label_prefs_v1';

function saveLabelPrefs() {
    try {
        const fields = {};
        document.querySelectorAll('.field-cb').forEach(cb => { fields[cb.name] = cb.checked; });
        const prefs = {
            format:   document.querySelector('input[name="format"]:checked')?.value || null,
            codeType: document.querySelector('input[name="code_type"]:checked')?.value || null,
            rotate:   document.querySelector('input[name="rotate_label"]')?.checked || false,
            customW:  document.getElementById('custom_w')?.value || null,
            customH:  document.getElementById('custom_h')?.value || null,
            fields,
        };
        localStorage.setItem(LABEL_PREFS_KEY, JSON.stringify(prefs));
    } catch (e) {
        // localStorage niet beschikbaar (bv. privénavigatie) -- gewoon negeren,
        // instellingen worden dan simpelweg niet onthouden.
    }
}

function loadLabelPrefs() {
    try {
        const raw = localStorage.getItem(LABEL_PREFS_KEY);
        if (!raw) return;
        const prefs = JSON.parse(raw);

        if (prefs.format) {
            const radio = document.querySelector('input[name="format"][value="' + prefs.format + '"]');
            if (radio) radio.checked = true;
        }
        if (prefs.codeType) {
            const radio = document.querySelector('input[name="code_type"][value="' + prefs.codeType + '"]');
            if (radio) radio.checked = true;
        }
        const rotateCb = document.querySelector('input[name="rotate_label"]');
        if (rotateCb) rotateCb.checked = !!prefs.rotate;
        if (prefs.customW) { const el = document.getElementById('custom_w'); if (el) el.value = prefs.customW; }
        if (prefs.customH) { const el = document.getElementById('custom_h'); if (el) el.value = prefs.customH; }
        if (prefs.fields) {
            document.querySelectorAll('.field-cb').forEach(cb => {
                if (Object.prototype.hasOwnProperty.call(prefs.fields, cb.name)) {
                    cb.checked = prefs.fields[cb.name];
                }
            });
        }
    } catch (e) {
        // Kapotte/onleesbare opgeslagen data -- gewoon negeren en met de
        // standaardinstellingen verdergaan.
    }
}

document.getElementById('labelForm').addEventListener('submit', saveLabelPrefs);

// Init: eerst eventueel onthouden instellingen herstellen, en pas dan de UI
// (max. velden, aangepast-formaat-velden, afdrukrichting) bijwerken op basis
// van het uiteindelijk geselecteerde formaat.
loadLabelPrefs();
updateFormatUI(document.querySelector('input[name="format"]:checked')?.value || 'medium');
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
