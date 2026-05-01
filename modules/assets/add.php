<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('add_assets');
requireLocation();

$errors  = [];
$success = '';
$data    = [];

// Pre-fill vanuit template
$templateId = (int)($_GET['template_id'] ?? 0);
if ($templateId && empty($data)) {
    $tpl = queryOne("SELECT * FROM asset_templates WHERE id = ? AND active = 1", [$templateId]);
    if ($tpl) {
        $data = [
            'type'                     => $tpl['asset_type'] ?? '',
            'brand'                    => $tpl['brand'] ?? '',
            'model'                    => $tpl['model'] ?? '',
            'depreciation_years'       => $tpl['depreciation_years'] ?? '',
            'manufacturer_url'         => $tpl['manufacturer_url'] ?? '',
            'operating_system'         => $tpl['operating_system'] ?? '',
            'ram'                      => $tpl['ram'] ?? '',
            'cpu'                      => $tpl['cpu'] ?? '',
            'business_critical'        => $tpl['business_critical'] ?? 0,
            'touchscreen_monitor_type' => $tpl['touchscreen_monitor_type'] ?? '',
            'monitor_count'            => $tpl['monitor_count'] ?? '',
            'notes'                    => $tpl['notes'] ?? '',
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token ongeldig.');
    }

    $data = [
        'location_id'              => getLocationId(),
        'asset_number'             => trim($_POST['asset_number'] ?? ''),
        'room'                     => trim($_POST['room'] ?? ''),
        'brand'                    => trim($_POST['brand'] ?? ''),
        'model'                    => trim($_POST['model'] ?? ''),
        'type'                     => trim($_POST['type'] ?? ''),
        'serial_number'            => trim($_POST['serial_number'] ?? ''),
        'status'                   => trim($_POST['status'] ?? 'Beschikbaar'),
        'assigned_to'              => trim($_POST['assigned_to'] ?? ''),
        'installed_date'           => trim($_POST['installed_date'] ?? '') ?: null,
        'purchase_date'            => trim($_POST['purchase_date'] ?? '') ?: null,
        'warranty_end_date'        => trim($_POST['warranty_end_date'] ?? '') ?: null,
        'depreciation_years'       => (int)($_POST['depreciation_years'] ?? 5),
        'autoupdate_expiry'        => trim($_POST['autoupdate_expiry'] ?? '') ?: null,
        'advised_replacement_date' => trim($_POST['advised_replacement_date'] ?? '') ?: null,
        'mac_address'              => trim($_POST['mac_address'] ?? ''),
        'lan_ip_address'           => trim($_POST['lan_ip_address'] ?? ''),
        'management_ip'            => trim($_POST['management_ip'] ?? ''),
        'most_recent_user'         => trim($_POST['most_recent_user'] ?? ''),
        'notes'                    => trim($_POST['notes'] ?? ''),
        'touchscreen_monitor_type' => trim($_POST['touchscreen_monitor_type'] ?? ''),
        'monitor_count'            => (int)($_POST['monitor_count'] ?? 1),
        'monitor_serial'           => trim($_POST['monitor_serial'] ?? ''),
        'in_repair_since'          => trim($_POST['in_repair_since'] ?? '') ?: null,
        'out_of_service_since'     => trim($_POST['out_of_service_since'] ?? '') ?: null,
        'ram'                      => trim($_POST['ram'] ?? ''),
        'cpu'                      => trim($_POST['cpu'] ?? ''),
        'operating_system'         => trim($_POST['operating_system'] ?? ''),
        'business_critical'        => isset($_POST['business_critical']) ? 1 : 0,
        'phone_number'             => trim($_POST['phone_number'] ?? ''),
        'access_point_number'      => trim($_POST['access_point_number'] ?? ''),
        'manufacturer_url'         => trim($_POST['manufacturer_url'] ?? ''),
        'created_by'               => getUserId(),
    ];

    // Auto-genereer assetnummer indien leeg
    if (empty($data['asset_number'])) {
        $data['asset_number'] = generateAssetNumber();
    }

    // Validatie
    if (empty($data['brand']))  $errors[] = 'Merk is verplicht';
    if (empty($data['model']))  $errors[] = 'Model is verplicht';
    if (!empty($data['mac_address']) && !isValidMAC($data['mac_address']))
        $errors[] = 'Ongeldig MAC adres (formaat: 00:11:22:33:44:55)';
    if (!empty($data['lan_ip_address']) && !isValidIP($data['lan_ip_address']))
        $errors[] = 'Ongeldig LAN IP adres';
    if (!empty($data['management_ip']) && !isValidIP($data['management_ip']))
        $errors[] = 'Ongeldig Management IP adres';

    if (empty($errors)) {
        try {
            $assetId = createAsset($data);
            registerBrandUsage($data['brand']);
            logAudit('INSERT', 'assets', $assetId, null, $data);

            // Sla pending koppelingen op
            $pendingRaw = $_POST['pending_relations'] ?? '[]';
            $pending = json_decode($pendingRaw, true) ?: [];
            foreach ($pending as $rel) {
                $relatedId    = (int)($rel['related_id'] ?? 0);
                $relationType = $rel['relation_type'] ?? 'peripheral';
                $relNotes     = $rel['notes'] ?? null;
                if ($relatedId && $relatedId !== $assetId) {
                    try {
                        execute(
                            "INSERT IGNORE INTO asset_relations (asset_id, related_id, relation_type, notes) VALUES (?, ?, ?, ?)",
                            [$assetId, $relatedId, $relationType, $relNotes ?: null]
                        );
                    } catch (Exception $e) { /* negeer duplicaten */ }
                }
            }

            $success = 'Asset succesvol toegevoegd!';
            $data    = [];
        } catch (Exception $e) {
            $errors[] = 'Fout bij opslaan: ' . $e->getMessage();
        }
    }
}

// Laad keuzelijsten
$brands     = getBrands();
$types      = getAssetTypes();
$statuses   = getAssetStatuses();
$rooms      = getRoomsByLocation(getLocationId());

$pageTitle = 'Asset toevoegen';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Asset toevoegen</h1>
    <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug naar overzicht</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <!-- SECTIE 1: Algemeen -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Algemeen</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>Assetnummer <small style="color:#6b7280;">(leeg = auto)</small></label>
                    <input type="text" name="asset_number" class="form-control"
                           value="<?= htmlspecialchars($data['asset_number'] ?? '') ?>"
                           placeholder="Automatisch gegenereerd">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statuses as $val => $lbl): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= ($data['status'] ?? 'Beschikbaar') === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Merk *</label>
                    <input type="text" name="brand" class="form-control" list="brands-list"
                           value="<?= htmlspecialchars($data['brand'] ?? '') ?>" required autocomplete="off">
                    <datalist id="brands-list">
                        <?php foreach ($brands as $b): ?>
                        <option value="<?= htmlspecialchars($b['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Model *</label>
                    <input type="text" name="model" class="form-control"
                           value="<?= htmlspecialchars($data['model'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Soort</label>
                    <input type="text" name="type" class="form-control" list="types-list"
                           value="<?= htmlspecialchars($data['type'] ?? '') ?>" autocomplete="off">
                    <datalist id="types-list">
                        <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Serienummer</label>
                    <input type="text" name="serial_number" class="form-control"
                           value="<?= htmlspecialchars($data['serial_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Ruimte</label>
                    <select name="room" class="form-control">
                        <option value="">- Kies ruimte -</option>
                        <?php foreach ($rooms as $room): ?>
                        <option value="<?= htmlspecialchars($room['name']) ?>"
                                <?= ($data['room'] ?? '') === $room['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($room['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fabrikant URL</label>
                    <input type="text" name="manufacturer_url" class="form-control"
                           value="<?= htmlspecialchars($data['manufacturer_url'] ?? '') ?>"
                           placeholder="https://..." style="width:100%;">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 2: Gebruik -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Gebruik</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>In gebruik bij</label>
                    <input type="text" name="assigned_to" class="form-control"
                           value="<?= htmlspecialchars($data['assigned_to'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Meest recente gebruiker</label>
                    <input type="text" name="most_recent_user" class="form-control"
                           value="<?= htmlspecialchars($data['most_recent_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Geïnstalleerd op</label>
                    <input type="date" name="installed_date" class="form-control"
                           value="<?= htmlspecialchars($data['installed_date'] ?? '') ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:25px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="business_critical" value="1"
                               <?= ($data['business_critical'] ?? 0) ? 'checked' : '' ?>>
                        <span>Bedrijfskritisch</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 3: Financieel -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Financieel & Garantie</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>Aankoopdatum</label>
                    <input type="date" name="purchase_date" class="form-control" id="purchase_date"
                           value="<?= htmlspecialchars($data['purchase_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Einde garantieperiode</label>
                    <input type="date" name="warranty_end_date" class="form-control"
                           value="<?= htmlspecialchars($data['warranty_end_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Afschrijvingstermijn (jaren)</label>
                    <input type="number" name="depreciation_years" class="form-control" id="depreciation_years"
                           value="<?= htmlspecialchars($data['depreciation_years'] ?? 5) ?>" min="1" max="20">
                </div>
                <div class="form-group">
                    <label>Advies vervangingsdatum</label>
                    <input type="date" name="advised_replacement_date" class="form-control" id="advised_replacement_date"
                           value="<?= htmlspecialchars($data['advised_replacement_date'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 4: Netwerk -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Netwerk</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>MAC-adres</label>
                    <input type="text" name="mac_address" class="form-control"
                           value="<?= htmlspecialchars($data['mac_address'] ?? '') ?>"
                           placeholder="00:11:22:33:44:55">
                </div>
                <div class="form-group">
                    <label>LAN IP-adres</label>
                    <input type="text" name="lan_ip_address" class="form-control"
                           value="<?= htmlspecialchars($data['lan_ip_address'] ?? '') ?>"
                           placeholder="192.168.1.100">
                </div>
                <div class="form-group">
                    <label>Management IP</label>
                    <input type="text" name="management_ip" class="form-control"
                           value="<?= htmlspecialchars($data['management_ip'] ?? '') ?>"
                           placeholder="192.168.1.100">
                </div>
                <div class="form-group">
                    <label>Access Point nummer</label>
                    <input type="text" name="access_point_number" class="form-control"
                           value="<?= htmlspecialchars($data['access_point_number'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 5: Hardware -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Hardware</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>RAM</label>
                    <input type="text" name="ram" class="form-control"
                           value="<?= htmlspecialchars($data['ram'] ?? '') ?>" placeholder="bijv. 16GB">
                </div>
                <div class="form-group">
                    <label>CPU</label>
                    <input type="text" name="cpu" class="form-control"
                           value="<?= htmlspecialchars($data['cpu'] ?? '') ?>" placeholder="bijv. Intel Core i7">
                </div>
                <div class="form-group">
                    <label>Besturingssysteem</label>
                    <input type="text" name="operating_system" class="form-control"
                           value="<?= htmlspecialchars($data['operating_system'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Touchscreen / Monitor type</label>
                    <input type="text" name="touchscreen_monitor_type" class="form-control"
                           value="<?= htmlspecialchars($data['touchscreen_monitor_type'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Aantal monitoren</label>
                    <input type="number" name="monitor_count" class="form-control"
                           value="<?= htmlspecialchars($data['monitor_count'] ?? 1) ?>" min="0" max="10">
                </div>
                <div class="form-group">
                    <label>Serienummer monitor</label>
                    <input type="text" name="monitor_serial" class="form-control"
                           value="<?= htmlspecialchars($data['monitor_serial'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 6: Beheer -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Beheer</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>Telefoon nummer</label>
                    <input type="text" name="phone_number" class="form-control"
                           value="<?= htmlspecialchars($data['phone_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Vervaldatum autoupdate</label>
                    <input type="date" name="autoupdate_expiry" class="form-control"
                           value="<?= htmlspecialchars($data['autoupdate_expiry'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>In reparatie sinds</label>
                    <input type="date" name="in_repair_since" class="form-control"
                           value="<?= htmlspecialchars($data['in_repair_since'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Buiten gebruik sinds</label>
                    <input type="date" name="out_of_service_since" class="form-control"
                           value="<?= htmlspecialchars($data['out_of_service_since'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 7: Opmerking -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Opmerking</h3>
            <div class="form-group">
                <textarea name="notes" class="form-control" rows="4"
                          placeholder="Vrije tekst..."><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Asset opslaan</button>
        <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">Annuleren</a>
    </div>
</form>

<script>
// Bereken vervangingsdatum automatisch
document.getElementById('purchase_date')?.addEventListener('change', calculateReplacement);
document.getElementById('depreciation_years')?.addEventListener('change', calculateReplacement);

function calculateReplacement() {
    const purchase = document.getElementById('purchase_date')?.value;
    const years    = parseInt(document.getElementById('depreciation_years')?.value) || 0;
    const field    = document.getElementById('advised_replacement_date');
    if (purchase && years && field && !field.value) {
        const date = new Date(purchase);
        date.setFullYear(date.getFullYear() + years);
        field.value = date.toISOString().split('T')[0];
    }
}
</script>

<!-- Gekoppelde assets bij aanmaken -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
            🔗 Gekoppelde assets
        </h3>
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:12px;">
            Koppel dit nieuwe asset direct aan bestaande apparaten.
            Koppelingen worden opgeslagen nadat het asset aangemaakt is.
        </p>

        <div id="pendingRelations" style="display:grid;gap:6px;margin-bottom:12px;"></div>
        <input type="hidden" name="pending_relations" id="pendingRelationsInput" value="[]">

        <button type="button" onclick="toggleAddRelationForm()" class="btn btn-secondary">
            + Koppeling toevoegen
        </button>

        <div id="addRelationForm" style="display:none;margin-top:15px;padding:15px;
             background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group" style="margin:0;position:relative;">
                    <label>Zoek asset</label>
                    <input type="text" id="addRelSearch" class="form-control"
                           placeholder="Assetnummer, merk, model..."
                           oninput="searchAddRelation(this.value)" autocomplete="off">
                    <div id="addRelResults" style="display:none;position:absolute;z-index:200;
                         background:white;border:1px solid #e5e7eb;border-radius:6px;
                         max-height:200px;overflow-y:auto;width:100%;
                         box-shadow:0 4px 10px rgba(0,0,0,0.1);top:100%;left:0;"></div>
                    <input type="hidden" id="addRelatedId">
                    <div id="addSelectedAsset" style="margin-top:4px;font-size:0.8rem;color:#059669;"></div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Type koppeling</label>
                    <select id="addRelationType" class="form-control">
                        <option value="peripheral">🔗 Randapparaat</option>
                        <option value="child">👶 Onderdeel van</option>
                        <option value="replacement">🔄 Vervanging van</option>
                        <option value="network">🌐 Netwerkkoppeling</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin:10px 0;">
                <label>Opmerking (optioneel)</label>
                <input type="text" id="addRelNotes" class="form-control"
                       placeholder="bijv. Linker monitor, Poort 12">
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="addPendingRelation()" class="btn btn-primary">
                    + Toevoegen aan lijst
                </button>
                <button type="button" onclick="toggleAddRelationForm()" class="btn btn-secondary">
                    Annuleren
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const relTypeLabels = {
    peripheral:  {label:'Randapparaat',     icon:'🔗'},
    child:       {label:'Onderdeel van',    icon:'👶'},
    replacement: {label:'Vervanging van',   icon:'🔄'},
    network:     {label:'Netwerkkoppeling', icon:'🌐'},
};

let pendingRelations = [];

function toggleAddRelationForm() {
    const form = document.getElementById('addRelationForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

let addRelTimeout;
function searchAddRelation(val) {
    clearTimeout(addRelTimeout);
    const box = document.getElementById('addRelResults');
    if (val.length < 2) { box.style.display = 'none'; return; }
    addRelTimeout = setTimeout(async () => {
        const resp = await fetch('<?= BASE_URL ?>/modules/assets/relations.php?search=' + encodeURIComponent(val));
        const data = await resp.json();
        if (!data.length) { box.style.display = 'none'; return; }
        box.innerHTML = data.map(a =>
            `<div onclick="selectAddRelation(${a.id}, '${a.asset_number.replace(/'/g,"\'")}', '${((a.brand||'')+' '+(a.model||'')).trim().replace(/'/g,"\'")}', '${(a.type||'').replace(/'/g,"\'")}' )"
                  style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:0.875rem;"
                  onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <strong>${a.asset_number}</strong>
                <span style="color:#6b7280;"> — ${a.brand||''} ${a.model||''} (${a.type||''})</span>
            </div>`
        ).join('');
        box.style.display = 'block';
    }, 300);
}

function selectAddRelation(id, number, name, type) {
    document.getElementById('addRelatedId').value = id;
    document.getElementById('addRelSearch').value = number;
    document.getElementById('addSelectedAsset').textContent = '✓ ' + number + ' — ' + name;
    document.getElementById('addRelResults').style.display = 'none';
}

function addPendingRelation() {
    const relatedId = document.getElementById('addRelatedId').value;
    const searchVal = document.getElementById('addRelSearch').value;
    if (!relatedId) { alert('Selecteer eerst een asset.'); return; }

    // Voorkom duplicaten
    if (pendingRelations.find(r => r.related_id == relatedId)) {
        alert('Dit asset is al toegevoegd.'); return;
    }

    const type  = document.getElementById('addRelationType').value;
    const notes = document.getElementById('addRelNotes').value;
    const rt    = relTypeLabels[type] || {label: type, icon: '🔗'};

    pendingRelations.push({related_id: relatedId, relation_type: type, notes: notes, label: searchVal + ' (' + rt.label + ')'});
    document.getElementById('pendingRelationsInput').value = JSON.stringify(pendingRelations);

    // Toon in lijst
    renderPending();

    // Reset form
    document.getElementById('addRelatedId').value = '';
    document.getElementById('addRelSearch').value = '';
    document.getElementById('addSelectedAsset').textContent = '';
    document.getElementById('addRelNotes').value = '';
    document.getElementById('addRelationForm').style.display = 'none';
}

function renderPending() {
    const container = document.getElementById('pendingRelations');
    if (!pendingRelations.length) { container.innerHTML = ''; return; }
    container.innerHTML = pendingRelations.map((r, i) => {
        const rt = relTypeLabels[r.relation_type] || {icon:'🔗', label: r.relation_type};
        return `<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;
                    background:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;font-size:0.875rem;">
            <span>${rt.icon}</span>
            <span style="flex:1;font-weight:600;">${r.label}</span>
            ${r.notes ? `<span style="color:#6b7280;font-size:0.8rem;">${r.notes}</span>` : ''}
            <button type="button" onclick="removePending(${i})"
                    style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1.1rem;">✕</button>
        </div>`;
    }).join('');
}

function removePending(i) {
    pendingRelations.splice(i, 1);
    document.getElementById('pendingRelationsInput').value = JSON.stringify(pendingRelations);
    renderPending();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#addRelSearch') && !e.target.closest('#addRelResults')) {
        document.getElementById('addRelResults').style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
