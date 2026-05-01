<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');
requireLocation();

$id    = (int)($_GET['id'] ?? 0);
$asset = getAssetById($id);

if (!$asset) {
    header('Location: ' . BASE_URL . '/modules/assets/?error=Asset+niet+gevonden');
    exit;
}

if (!canEditLocation((int)$asset['location_id'])) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#dc2626;">Geen toegang</h2>
        <p>U heeft geen bewerkrechten voor deze locatie.</p>
        <a href="' . BASE_URL . '/modules/assets/">← Terug</a>
    </div>');
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token ongeldig.');
    }

    $oldValues = $asset;

    $data = [
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
    ];

    // Validatie
    if (empty($data['brand']))  $errors[] = 'Merk is verplicht';
    if (empty($data['model']))  $errors[] = 'Model is verplicht';
    if (!empty($data['mac_address']) && !isValidMAC($data['mac_address']))
        $errors[] = 'Ongeldig MAC adres';
    if (!empty($data['lan_ip_address']) && !isValidIP($data['lan_ip_address']))
        $errors[] = 'Ongeldig LAN IP adres';
    if (!empty($data['management_ip']) && !isValidIP($data['management_ip']))
        $errors[] = 'Ongeldig Management IP adres';

    if (empty($errors)) {
        try {
            updateAsset($id, $data);
            registerBrandUsage($data['brand']);
            logAudit('UPDATE', 'assets', $id, $oldValues, array_merge($asset, $data));
            $success = 'Asset succesvol bijgewerkt!';
            $asset   = array_merge($asset, $data);
        } catch (Exception $e) {
            $errors[] = 'Fout bij opslaan: ' . $e->getMessage();
        }
    }
}

// Keuzelijsten
$brands   = getBrands();
$types    = getAssetTypes();
$statuses = getAssetStatuses();
$rooms    = getRoomsByLocation((int)$asset['location_id']);

$pageTitle = 'Asset bewerken';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Asset bewerken: <?= htmlspecialchars($asset['asset_number']) ?></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">Bekijken</a>
        <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug</a>
    </div>
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
            <div style="margin-bottom:15px;padding:10px;background:#f8fafc;border-radius:6px;">
                <strong>Assetnummer:</strong> <?= htmlspecialchars($asset['asset_number']) ?>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statuses as $val => $lbl): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $asset['status'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Merk *</label>
                    <input type="text" name="brand" class="form-control" list="brands-list"
                           value="<?= htmlspecialchars($asset['brand'] ?? '') ?>" required autocomplete="off">
                    <datalist id="brands-list">
                        <?php foreach ($brands as $b): ?>
                        <option value="<?= htmlspecialchars($b['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Model *</label>
                    <input type="text" name="model" class="form-control"
                           value="<?= htmlspecialchars($asset['model'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Soort</label>
                    <input type="text" name="type" class="form-control" list="types-list"
                           value="<?= htmlspecialchars($asset['type'] ?? '') ?>" autocomplete="off">
                    <datalist id="types-list">
                        <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Serienummer</label>
                    <input type="text" name="serial_number" class="form-control"
                           value="<?= htmlspecialchars($asset['serial_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Ruimte</label>
                    <select name="room" class="form-control">
                        <option value="">- Kies ruimte -</option>
                        <?php foreach ($rooms as $room): ?>
                        <option value="<?= htmlspecialchars($room['name']) ?>"
                                <?= $asset['room'] === $room['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($room['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fabrikant URL</label>
                    <input type="text" name="manufacturer_url" class="form-control"
                           value="<?= htmlspecialchars($asset['manufacturer_url'] ?? '') ?>"
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
                           value="<?= htmlspecialchars($asset['assigned_to'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Meest recente gebruiker</label>
                    <input type="text" name="most_recent_user" class="form-control"
                           value="<?= htmlspecialchars($asset['most_recent_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Geïnstalleerd op</label>
                    <input type="date" name="installed_date" class="form-control"
                           value="<?= htmlspecialchars($asset['installed_date'] ?? '') ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:25px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="business_critical" value="1"
                               <?= ($asset['business_critical'] ?? 0) ? 'checked' : '' ?>>
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
                           value="<?= htmlspecialchars($asset['purchase_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Einde garantieperiode</label>
                    <input type="date" name="warranty_end_date" class="form-control"
                           value="<?= htmlspecialchars($asset['warranty_end_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Afschrijvingstermijn (jaren)</label>
                    <input type="number" name="depreciation_years" class="form-control" id="depreciation_years"
                           value="<?= htmlspecialchars($asset['depreciation_years'] ?? 5) ?>" min="1" max="20">
                </div>
                <div class="form-group">
                    <label>Advies vervangingsdatum</label>
                    <input type="date" name="advised_replacement_date" class="form-control" id="advised_replacement_date"
                           value="<?= htmlspecialchars($asset['advised_replacement_date'] ?? '') ?>">
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
                           value="<?= htmlspecialchars($asset['mac_address'] ?? '') ?>"
                           placeholder="00:11:22:33:44:55">
                </div>
                <div class="form-group">
                    <label>LAN IP-adres</label>
                    <input type="text" name="lan_ip_address" class="form-control"
                           value="<?= htmlspecialchars($asset['lan_ip_address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Management IP</label>
                    <input type="text" name="management_ip" class="form-control"
                           value="<?= htmlspecialchars($asset['management_ip'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Access Point nummer</label>
                    <input type="text" name="access_point_number" class="form-control"
                           value="<?= htmlspecialchars($asset['access_point_number'] ?? '') ?>">
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
                           value="<?= htmlspecialchars($asset['ram'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>CPU</label>
                    <input type="text" name="cpu" class="form-control"
                           value="<?= htmlspecialchars($asset['cpu'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Besturingssysteem</label>
                    <input type="text" name="operating_system" class="form-control"
                           value="<?= htmlspecialchars($asset['operating_system'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Monitor type</label>
                    <input type="text" name="touchscreen_monitor_type" class="form-control"
                           value="<?= htmlspecialchars($asset['touchscreen_monitor_type'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Aantal monitoren</label>
                    <input type="number" name="monitor_count" class="form-control"
                           value="<?= htmlspecialchars($asset['monitor_count'] ?? 1) ?>" min="0" max="10">
                </div>
                <div class="form-group">
                    <label>Serienummer monitor</label>
                    <input type="text" name="monitor_serial" class="form-control"
                           value="<?= htmlspecialchars($asset['monitor_serial'] ?? '') ?>">
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
                           value="<?= htmlspecialchars($asset['phone_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Vervaldatum autoupdate</label>
                    <input type="date" name="autoupdate_expiry" class="form-control"
                           value="<?= htmlspecialchars($asset['autoupdate_expiry'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>In reparatie sinds</label>
                    <input type="date" name="in_repair_since" class="form-control"
                           value="<?= htmlspecialchars($asset['in_repair_since'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Buiten gebruik sinds</label>
                    <input type="date" name="out_of_service_since" class="form-control"
                           value="<?= htmlspecialchars($asset['out_of_service_since'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTIE 7: Opmerking -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Opmerking</h3>
            <div class="form-group">
                <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars($asset['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Fotogalerij sectie -->
    <?php $assetImages = getAssetImages($asset['id']); ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;
                        align-items:center;margin-bottom:15px;">
                <h3 style="margin:0;color:#1a2332;">Foto's</h3>
                <a href="<?= BASE_URL ?>/modules/assets/images.php?id=<?= $asset['id'] ?>" 
                   class="btn btn-secondary">
                    📷 Foto's beheren
                </a>
            </div>
            <?php if (empty($assetImages)): ?>
                <p style="color:#6b7280;">
                    Nog geen foto's toegevoegd. 
                    <a href="<?= BASE_URL ?>/modules/assets/images.php?id=<?= $asset['id'] ?>">
                        Foto's toevoegen →
                    </a>
                </p>
            <?php else: ?>
                <div style="display:grid;
                            grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
                            gap:10px;">
                    <?php foreach (array_slice($assetImages, 0, 6) as $index => $image): ?>
                    <div style="position:relative;">
                        <img src="<?= BASE_URL ?>/assets/uploads/asset_images/<?= htmlspecialchars($image['filename']) ?>" 
                             style="width:100%;height:90px;object-fit:cover;
                                    border-radius:8px;display:block;">
                        <?php if ($index === 0): ?>
                        <span style="position:absolute;top:5px;left:5px;
                                     background:#2563eb;color:#fff;
                                     padding:2px 7px;border-radius:999px;
                                     font-size:0.7rem;font-weight:600;">
                            Hoofdfoto
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($assetImages) > 6): ?>
                    <div style="display:flex;align-items:center;justify-content:center;
                                height:90px;background:#f1f5f9;border-radius:8px;
                                color:#64748b;font-weight:600;">
                        +<?= count($assetImages) - 6 ?> meer
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:30px;">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">Annuleren</a>
    </div>
</form>

<script>
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

<!-- Gekoppelde assets — volledig beheer in edit -->
<?php
$editRelations = query(
    "SELECT ar.*, a.asset_number, a.brand, a.model, a.type, a.status, ar.id as rel_id
     FROM asset_relations ar
     JOIN assets a ON ar.related_id = a.id
     WHERE ar.asset_id = ?
     ORDER BY ar.relation_type, a.asset_number",
    [$asset['id']]
);
$parentEditRelations = query(
    "SELECT ar.*, a.asset_number, a.brand, a.model, a.type, ar.id as rel_id
     FROM asset_relations ar
     JOIN assets a ON ar.asset_id = a.id
     WHERE ar.related_id = ?
     ORDER BY a.asset_number",
    [$asset['id']]
);
$relTypeLabels = [
    'peripheral'  => ['label' => 'Randapparaat',     'icon' => '🔗'],
    'child'       => ['label' => 'Onderdeel van',    'icon' => '👶'],
    'replacement' => ['label' => 'Vervanging van',   'icon' => '🔄'],
    'network'     => ['label' => 'Netwerkkoppeling', 'icon' => '🌐'],
];
?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
            🔗 Gekoppelde assets
        </h3>

        <!-- Bestaande koppelingen -->
        <?php if (!empty($editRelations)): ?>
        <div style="margin-bottom:15px;">
            <div style="font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:8px;">
                Gekoppeld aan dit asset
            </div>
            <div style="display:grid;gap:6px;">
                <?php foreach ($editRelations as $rel): ?>
                <?php $rt = $relTypeLabels[$rel['relation_type']] ?? ['label'=>$rel['relation_type'],'icon'=>'🔗']; ?>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                            background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;">
                    <span style="font-size:1.1rem;"><?= $rt['icon'] ?></span>
                    <div style="flex:1;">
                        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $rel['related_id'] ?>"
                           style="font-weight:600;color:#2563eb;text-decoration:none;font-size:0.9rem;">
                            <?= htmlspecialchars($rel['asset_number']) ?>
                        </a>
                        <span style="color:#6b7280;font-size:0.8rem;">
                            — <?= htmlspecialchars(trim(($rel['brand']??'').' '.($rel['model']??''))) ?>
                            (<?= htmlspecialchars($rel['type']??'') ?>)
                        </span>
                        <?php if ($rel['notes']): ?>
                        <span style="color:#6b7280;font-size:0.78rem;display:block;">
                            <?= htmlspecialchars($rel['notes']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <span style="font-size:0.75rem;background:#e5e7eb;padding:2px 8px;border-radius:4px;">
                        <?= $rt['label'] ?>
                    </span>
                    <form method="POST" action="<?= BASE_URL ?>/modules/assets/relations.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="relation_id" value="<?= $rel['rel_id'] ?>">
                        <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                        <input type="hidden" name="redirect" value="edit">
                        <button type="submit" onclick="return confirm('Koppeling verwijderen?')"
                                style="background:none;border:none;color:#ef4444;cursor:pointer;
                                       font-size:1.1rem;padding:4px;" title="Verwijderen">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($parentEditRelations)): ?>
        <div style="margin-bottom:15px;">
            <div style="font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:8px;">
                Dit asset is gekoppeld aan
            </div>
            <div style="display:grid;gap:6px;">
                <?php foreach ($parentEditRelations as $rel): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                            background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;">
                    <span>↑</span>
                    <div style="flex:1;">
                        <a href="<?= BASE_URL ?>/modules/assets/edit.php?id=<?= $rel['asset_id'] ?>"
                           style="font-weight:600;color:#2563eb;text-decoration:none;font-size:0.9rem;">
                            <?= htmlspecialchars($rel['asset_number']) ?>
                        </a>
                        <span style="color:#6b7280;font-size:0.8rem;">
                            — <?= htmlspecialchars(trim(($rel['brand']??'').' '.($rel['model']??''))) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($editRelations) && empty($parentEditRelations)): ?>
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:15px;">
            Nog geen koppelingen. Voeg hieronder een koppeling toe.
        </p>
        <?php endif; ?>

        <!-- Koppeling toevoegen -->
        <div style="border-top:1px solid #e5e7eb;padding-top:15px;margin-top:5px;">
            <button type="button" onclick="toggleEditRelationForm()" class="btn btn-secondary">
                + Koppeling toevoegen
            </button>
            <div id="editRelationForm" style="display:none;margin-top:15px;padding:15px;
                 background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group" style="margin:0;position:relative;">
                    <label>Zoek asset</label>
                    <input type="text" id="editRelationSearch" class="form-control"
                           placeholder="Assetnummer, merk, model..."
                           oninput="searchEditRelation(this.value)" autocomplete="off">
                    <div id="editRelationResults" style="display:none;position:absolute;z-index:200;
                         background:white;border:1px solid #e5e7eb;border-radius:6px;
                         max-height:200px;overflow-y:auto;width:100%;
                         box-shadow:0 4px 10px rgba(0,0,0,0.1);top:100%;left:0;">
                    </div>
                    <input type="hidden" id="editRelatedId">
                    <div id="editSelectedAsset" style="margin-top:4px;font-size:0.8rem;color:#059669;"></div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Type koppeling</label>
                    <select id="editRelationType" class="form-control">
                        <option value="peripheral">🔗 Randapparaat</option>
                        <option value="child">👶 Onderdeel van</option>
                        <option value="replacement">🔄 Vervanging van</option>
                        <option value="network">🌐 Netwerkkoppeling</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin:10px 0;">
                <label>Opmerking (optioneel)</label>
                <input type="text" id="editRelationNotes" class="form-control"
                       placeholder="bijv. Linker monitor, Poort 12">
            </div>
            <div style="display:flex;gap:10px;margin-top:5px;">
                <button type="button" onclick="saveEditRelation()" class="btn btn-primary">
                    🔗 Koppeling opslaan
                </button>
                <button type="button" onclick="toggleEditRelationForm()" class="btn btn-secondary">
                    Annuleren
                </button>
            </div>
            </div><!-- end editRelationForm -->
        </div>
    </div>
</div>

<form id="addRelationForm" method="POST"
      action="<?= BASE_URL ?>/modules/assets/relations.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
    <input type="hidden" name="related_id" id="formRelatedId">
    <input type="hidden" name="relation_type" id="formRelationType">
    <input type="hidden" name="notes" id="formRelationNotes">
    <input type="hidden" name="redirect" value="edit">
</form>

<script>
let editRelSearchTimeout;

function toggleEditRelationForm() {
    const form = document.getElementById('editRelationForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function searchEditRelation(val) {
    clearTimeout(editRelSearchTimeout);
    const box = document.getElementById('editRelationResults');
    if (val.length < 2) { box.style.display = 'none'; return; }

    editRelSearchTimeout = setTimeout(async () => {
        const resp = await fetch('<?= BASE_URL ?>/modules/assets/relations.php?search=' +
            encodeURIComponent(val) + '&exclude=<?= $asset['id'] ?>');
        const data = await resp.json();
        if (!data.length) { box.style.display = 'none'; return; }
        box.innerHTML = data.map(a =>
            `<div onclick="selectEditRelation(${a.id}, '${a.asset_number.replace(/'/g,"\'")}', '${((a.brand||'')+ ' ' +(a.model||'')).trim().replace(/'/g,"\'")}', '${(a.type||'').replace(/'/g,"\'")}' )"
                  style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:0.875rem;"
                  onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <strong>${a.asset_number}</strong>
                <span style="color:#6b7280;"> — ${a.brand||''} ${a.model||''} (${a.type||''})</span>
            </div>`
        ).join('');
        box.style.display = 'block';
    }, 300);
}

function selectEditRelation(id, number, name, type) {
    document.getElementById('editRelatedId').value = id;
    document.getElementById('editRelationSearch').value = number;
    document.getElementById('editSelectedAsset').textContent = '✓ ' + number + ' — ' + name;
    document.getElementById('editRelationResults').style.display = 'none';
}

function saveEditRelation() {
    const relatedId = document.getElementById('editRelatedId').value;
    if (!relatedId) { alert('Selecteer eerst een asset om te koppelen.'); return; }
    document.getElementById('formRelatedId').value = relatedId;
    document.getElementById('formRelationType').value = document.getElementById('editRelationType').value;
    document.getElementById('formRelationNotes').value = document.getElementById('editRelationNotes').value;
    document.getElementById('addRelationForm').submit();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#editRelationSearch') && !e.target.closest('#editRelationResults')) {
        document.getElementById('editRelationResults').style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
