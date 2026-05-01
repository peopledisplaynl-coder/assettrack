<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_settings');

if (getRole() !== 'superadmin') {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#dc2626;">Geen toegang</h2>
        <p>Alleen superadmin kan organisaties en locaties beheren.</p>
        <a href="' . BASE_URL . '/modules/settings/">Terug</a>
    </div>');
}

$action = $_GET['action'] ?? 'organisations';
$editId = (int)($_GET['edit'] ?? 0);
$errors = [];
$success = '';

$uploadDir = __DIR__ . '/../../assets/uploads/location_logos';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ── Logo upload helper ────────────────────────────────────────────
function doLogoUpload(string $fileKey, string $table, int $id): ?string {
    global $uploadDir;
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $img = $_FILES[$fileKey];
    $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) return null;
    if ($img['size'] > 2 * 1024 * 1024) return null;
    $old = queryOne("SELECT logo_path FROM $table WHERE id=?", [$id]);
    if (!empty($old['logo_path'])) {
        $f = __DIR__ . '/../../' . ltrim($old['logo_path'], '/');
        if (file_exists($f)) @unlink($f);
    }
    $fname = $table . '_' . $id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($img['tmp_name'], $uploadDir . '/' . $fname)) {
        return BASE_URL . '/assets/uploads/location_logos/' . $fname;
    }
    return null;
}

function doRemoveLogo(string $table, int $id): void {
    $old = queryOne("SELECT logo_path FROM $table WHERE id=?", [$id]);
    if (!empty($old['logo_path'])) {
        $f = __DIR__ . '/../../' . ltrim($old['logo_path'], '/');
        if (file_exists($f)) @unlink($f);
    }
    execute("UPDATE $table SET logo_path=NULL WHERE id=?", [$id]);
}

// ══════════════════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $pa = $_POST['post_action'] ?? '';

    // Organisaties
    if ($pa === 'org_add') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { $errors[] = 'Naam is verplicht.'; }
        else {
            execute("INSERT INTO organisations (name,address,city,zip,phone,email,website,active) VALUES (?,?,?,?,?,?,?,1)",
                [$name, $_POST['address']??null, $_POST['city']??null, $_POST['zip']??null,
                 $_POST['phone']??null, $_POST['email']??null, $_POST['website']??null]);
            $success = "Organisatie '$name' aangemaakt."; $editId = 0;
        }
    }
    if ($pa === 'org_update') {
        $rid  = (int)($_POST['record_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$rid || !$name) { $errors[] = 'Naam is verplicht.'; }
        else {
            $logo = queryOne("SELECT logo_path FROM organisations WHERE id=?", [$rid])['logo_path'] ?? null;
            if (isset($_POST['remove_logo'])) { doRemoveLogo('organisations', $rid); $logo = null; }
            $nl = doLogoUpload('logo', 'organisations', $rid);
            if ($nl) $logo = $nl;
            execute("UPDATE organisations SET name=?,address=?,city=?,zip=?,phone=?,email=?,website=?,logo_path=? WHERE id=?",
                [$name, $_POST['address']??null, $_POST['city']??null, $_POST['zip']??null,
                 $_POST['phone']??null, $_POST['email']??null, $_POST['website']??null, $logo, $rid]);
            $success = "Organisatie bijgewerkt."; $editId = 0;
        }
    }

    // Locaties
    if ($pa === 'loc_add') {
        $name  = trim($_POST['name'] ?? '');
        $orgId = (int)($_POST['organisation_id'] ?? 0);
        if (!$name || !$orgId) { $errors[] = 'Naam en organisatie zijn verplicht.'; }
        else {
            execute("INSERT INTO locations (organisation_id,name,address,city,zip,phone,email,active) VALUES (?,?,?,?,?,?,?,1)",
                [$orgId, $name, $_POST['address']??null, $_POST['city']??null,
                 $_POST['zip']??null, $_POST['phone']??null, $_POST['email']??null]);
            $success = "Locatie '$name' aangemaakt."; $editId = 0;
        }
    }
    if ($pa === 'loc_update') {
        $rid  = (int)($_POST['record_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$rid || !$name) { $errors[] = 'Naam is verplicht.'; }
        else {
            $logo = queryOne("SELECT logo_path FROM locations WHERE id=?", [$rid])['logo_path'] ?? null;
            if (isset($_POST['remove_logo'])) { doRemoveLogo('locations', $rid); $logo = null; }
            $nl = doLogoUpload('logo', 'locations', $rid);
            if ($nl) $logo = $nl;
            $color = trim($_POST['theme_color'] ?? '');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = null;
            execute("UPDATE locations SET name=?,organisation_id=?,address=?,city=?,zip=?,phone=?,email=?,logo_path=?,theme_color=? WHERE id=?",
                [$name, (int)$_POST['organisation_id'], $_POST['address']??null, $_POST['city']??null,
                 $_POST['zip']??null, $_POST['phone']??null, $_POST['email']??null, $logo, $color, $rid]);
            $success = "Locatie bijgewerkt."; $editId = 0;
        }
    }

    // Ruimtes
    if ($pa === 'room_add') {
        $name  = trim($_POST['name'] ?? '');
        $locId = (int)($_POST['location_id'] ?? 0);
        if (!$name || !$locId) { $errors[] = 'Naam en locatie zijn verplicht.'; }
        else {
            execute("INSERT INTO rooms (location_id,name,location_desc,active) VALUES (?,?,?,1)",
                [$locId, $name, $_POST['location_desc']??null]);
            $success = "Ruimte '$name' aangemaakt."; $editId = 0;
        }
    }
    if ($pa === 'room_update') {
        $rid   = (int)($_POST['record_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $locId = (int)($_POST['location_id'] ?? 0);
        if (!$rid || !$name || !$locId) { $errors[] = 'Naam en locatie zijn verplicht.'; }
        else {
            execute("UPDATE rooms SET name=?,location_id=?,location_desc=? WHERE id=?",
                [$name, $locId, $_POST['location_desc']??null, $rid]);
            $success = "Ruimte bijgewerkt."; $editId = 0;
        }
    }
    if ($pa === 'room_delete') {
        $rid = (int)($_POST['record_id'] ?? 0);
        if ($rid) { execute("UPDATE rooms SET active=0 WHERE id=?", [$rid]); $success = "Ruimte verwijderd."; }
    }
}

// ── Data laden ────────────────────────────────────────────────────
$organisations = query("SELECT * FROM organisations WHERE active=1 ORDER BY name");
$locations     = query("SELECT l.*, o.name as org_name FROM locations l
                        JOIN organisations o ON l.organisation_id=o.id WHERE l.active=1 ORDER BY o.name,l.name");
$filterLocId   = (int)($_GET['loc_filter'] ?? 0);
$rq = "SELECT r.*,l.name as loc_name,o.name as org_name FROM rooms r
       JOIN locations l ON r.location_id=l.id JOIN organisations o ON l.organisation_id=o.id
       WHERE r.active=1";
$rp = [];
if ($filterLocId) { $rq .= " AND r.location_id=?"; $rp[] = $filterLocId; }
$rq .= " ORDER BY o.name,l.name,r.name";
$rooms = query($rq, $rp);

$editOrg  = ($action==='organisations' && $editId) ? queryOne("SELECT * FROM organisations WHERE id=?",[$editId]) : null;
$editLoc  = ($action==='locations'    && $editId) ? queryOne("SELECT * FROM locations WHERE id=?",[$editId])     : null;
$editRoom = ($action==='rooms'        && $editId) ? queryOne("SELECT * FROM rooms WHERE id=?",[$editId])         : null;

$pageTitle = 'Organisatie & Locaties';
include __DIR__ . '/../../templates/header.php';

// ── Herbruikbaar formulier velden ─────────────────────────────────
function formField(string $label, string $name, string $val='', string $type='text', bool $required=false): void {
    $req = $required ? '<span style="color:#ef4444;">*</span>' : '';
    echo "<div class='form-group'>
          <label>$label $req</label>
          <input type='$type' name='$name' class='form-control' value='" . htmlspecialchars($val) . "'" . ($required?" required":"") . ">
          </div>";
}
?>

<div class="page-header">
    <h1>🏢 Organisatie & Locaties</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid #e5e7eb;">
    <?php foreach(['organisations'=>'🏢 Organisaties','locations'=>'📍 Locaties','rooms'=>'🚪 Ruimtes'] as $tab=>$lbl): ?>
    <a href="?action=<?= $tab ?>" style="padding:10px 20px;text-decoration:none;font-weight:600;font-size:0.9rem;
       margin-bottom:-2px;border-bottom:3px solid <?= $action===$tab?'#2563eb':'transparent' ?>;
       color:<?= $action===$tab?'#2563eb':'#64748b' ?>;"><?= $lbl ?></a>
    <?php endforeach; ?>
</div>

<?php // ═══ ORGANISATIES ═══════════════════════════════════════════
if ($action === 'organisations'): ?>
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">
    <div class="card" style="margin:0;"><div class="card-body">
        <h3 style="margin:0 0 16px;">Organisaties (<?= count($organisations) ?>)</h3>
        <table class="data-table"><thead><tr><th></th><th>Naam</th><th>Stad</th><th>Acties</th></tr></thead><tbody>
        <?php foreach($organisations as $org): ?>
        <tr>
            <td style="width:44px;"><?php if(!empty($org['logo_path'])): ?>
                <img src="<?= htmlspecialchars($org['logo_path']) ?>" style="width:36px;height:36px;object-fit:contain;border-radius:4px;border:1px solid #e5e7eb;">
                <?php else: ?><div style="width:36px;height:36px;background:#f1f5f9;border-radius:4px;display:flex;align-items:center;justify-content:center;">🏢</div><?php endif; ?></td>
            <td><strong><?= htmlspecialchars($org['name']) ?></strong>
                <?php if($org['email']): ?><div style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($org['email']) ?></div><?php endif; ?></td>
            <td><?= htmlspecialchars($org['city']??'') ?></td>
            <td><a href="?action=organisations&edit=<?= $org['id'] ?>" class="btn btn-sm btn-secondary">✏️ Bewerken</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($organisations)): ?><tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">Nog geen organisaties.</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <div class="card" style="margin:0;"><div class="card-body">
        <h3 style="margin:0 0 16px;"><?= $editOrg ? '✏️ Bewerken' : '+ Toevoegen' ?></h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="post_action" value="<?= $editOrg ? 'org_update' : 'org_add' ?>">
            <?php if($editOrg): ?><input type="hidden" name="record_id" value="<?= $editOrg['id'] ?>"><?php endif; ?>
            <?php formField('Naam','name',$editOrg['name']??'','text',true); ?>
            <?php formField('Adres','address',$editOrg['address']??''); ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <?php formField('Stad','city',$editOrg['city']??''); ?>
                <?php formField('Postcode','zip',$editOrg['zip']??''); ?>
            </div>
            <?php formField('Telefoon','phone',$editOrg['phone']??''); ?>
            <?php formField('E-mail','email',$editOrg['email']??'','email'); ?>
            <?php formField('Website','website',$editOrg['website']??''); ?>
            <div class="form-group"><label>Logo</label>
                <?php if(!empty($editOrg['logo_path'])): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <img src="<?= htmlspecialchars($editOrg['logo_path']) ?>" style="height:36px;max-width:120px;object-fit:contain;border:1px solid #e5e7eb;border-radius:5px;padding:3px;">
                    <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer;font-size:0.82rem;">
                        <input type="checkbox" name="remove_logo" value="1"><span style="color:#ef4444;">Logo verwijderen</span>
                    </label>
                </div><?php endif; ?>
                <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                <small style="color:#6b7280;">PNG/SVG aanbevolen, max 2MB.</small>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;"><?= $editOrg ? '💾 Opslaan' : '+ Toevoegen' ?></button>
                <?php if($editOrg): ?><a href="?action=organisations" class="btn btn-secondary">Annuleren</a><?php endif; ?>
            </div>
        </form>
    </div></div>
</div>

<?php // ═══ LOCATIES ════════════════════════════════════════════════
elseif ($action === 'locations'): ?>
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">
    <div class="card" style="margin:0;"><div class="card-body">
        <h3 style="margin:0 0 16px;">Locaties (<?= count($locations) ?>)</h3>
        <table class="data-table"><thead><tr><th></th><th>Naam</th><th>Organisatie</th><th>Stad</th><th>Acties</th></tr></thead><tbody>
        <?php foreach($locations as $loc): ?>
        <tr>
            <td style="width:44px;"><?php if(!empty($loc['logo_path'])): ?>
                <img src="<?= htmlspecialchars($loc['logo_path']) ?>" style="width:36px;height:36px;object-fit:contain;border-radius:4px;border:1px solid #e5e7eb;">
                <?php else: ?>
                <div style="width:36px;height:36px;border-radius:4px;background:<?= !empty($loc['theme_color'])?$loc['theme_color']:'#f1f5f9' ?>;display:flex;align-items:center;justify-content:center;">📍</div>
                <?php endif; ?></td>
            <td><strong><?= htmlspecialchars($loc['name']) ?></strong>
                <?php if(!empty($loc['theme_color'])): ?>
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $loc['theme_color'] ?>;margin-left:4px;vertical-align:middle;"></span>
                <?php endif; ?></td>
            <td><?= htmlspecialchars($loc['org_name']) ?></td>
            <td><?= htmlspecialchars($loc['city']??'') ?></td>
            <td><a href="?action=locations&edit=<?= $loc['id'] ?>" class="btn btn-sm btn-secondary">✏️ Bewerken</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($locations)): ?><tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;">Nog geen locaties.</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <div class="card" style="margin:0;"><div class="card-body">
        <h3 style="margin:0 0 16px;"><?= $editLoc ? '✏️ Bewerken' : '+ Toevoegen' ?></h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="post_action" value="<?= $editLoc ? 'loc_update' : 'loc_add' ?>">
            <?php if($editLoc): ?><input type="hidden" name="record_id" value="<?= $editLoc['id'] ?>"><?php endif; ?>
            <div class="form-group"><label>Organisatie <span style="color:#ef4444;">*</span></label>
                <select name="organisation_id" class="form-control" required>
                    <option value="">— Kies organisatie —</option>
                    <?php foreach($organisations as $org): ?>
                    <option value="<?= $org['id'] ?>" <?= ($editLoc['organisation_id']??0)==$org['id']?'selected':'' ?>><?= htmlspecialchars($org['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php formField('Naam','name',$editLoc['name']??'','text',true); ?>
            <?php formField('Adres','address',$editLoc['address']??''); ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <?php formField('Stad','city',$editLoc['city']??''); ?>
                <?php formField('Postcode','zip',$editLoc['zip']??''); ?>
            </div>
            <?php formField('Telefoon','phone',$editLoc['phone']??''); ?>
            <?php formField('E-mail','email',$editLoc['email']??'','email'); ?>
            <div class="form-group"><label>Locatiekleur</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="color" name="theme_color" value="<?= htmlspecialchars($editLoc['theme_color']??'#2563eb') ?>"
                           style="width:44px;height:36px;border:1.5px solid #d1d5db;border-radius:6px;padding:2px;cursor:pointer;">
                    <span style="font-size:0.82rem;color:#64748b;">Zichtbaar in header en locatie badge</span>
                </div>
            </div>
            <div class="form-group"><label>Logo</label>
                <?php if(!empty($editLoc['logo_path'])): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <img src="<?= htmlspecialchars($editLoc['logo_path']) ?>" style="height:36px;max-width:120px;object-fit:contain;border:1px solid #e5e7eb;border-radius:5px;padding:3px;">
                    <label style="display:flex;align-items:center;gap:5px;font-weight:normal;cursor:pointer;font-size:0.82rem;">
                        <input type="checkbox" name="remove_logo" value="1"><span style="color:#ef4444;">Logo verwijderen</span>
                    </label>
                </div><?php endif; ?>
                <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                <small style="color:#6b7280;">PNG/SVG aanbevolen, max 2MB.</small>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;"><?= $editLoc ? '💾 Opslaan' : '+ Toevoegen' ?></button>
                <?php if($editLoc): ?><a href="?action=locations" class="btn btn-secondary">Annuleren</a><?php endif; ?>
            </div>
        </form>
    </div></div>
</div>

<?php // ═══ RUIMTES ════════════════════════════════════════════════
elseif ($action === 'rooms'): ?>
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">
    <div class="card" style="margin:0;"><div class="card-body">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
            <h3 style="margin:0;flex:1;">Ruimtes (<?= count($rooms) ?>)</h3>
            <form method="GET" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="action" value="rooms">
                <select name="loc_filter" class="form-control" style="width:200px;" onchange="this.form.submit()">
                    <option value="">Alle locaties</option>
                    <?php foreach($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $filterLocId===$loc['id']?'selected':'' ?>><?= htmlspecialchars($loc['org_name'].' — '.$loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if($filterLocId): ?><a href="?action=rooms" class="btn btn-sm btn-secondary">✕</a><?php endif; ?>
            </form>
        </div>
        <table class="data-table"><thead><tr><th>Ruimte</th><th>Locatie</th><th>Beschrijving</th><th>Acties</th></tr></thead><tbody>
        <?php foreach($rooms as $room): ?>
        <tr>
            <td><strong><?= htmlspecialchars($room['name']) ?></strong></td>
            <td><span style="font-size:0.8rem;color:#94a3b8;"><?= htmlspecialchars($room['org_name']) ?></span><br><?= htmlspecialchars($room['loc_name']) ?></td>
            <td style="color:#64748b;font-size:0.82rem;"><?= htmlspecialchars($room['location_desc']??'') ?></td>
            <td><div style="display:flex;gap:5px;">
                <a href="?action=rooms&edit=<?= $room['id'] ?><?= $filterLocId?'&loc_filter='.$filterLocId:'' ?>" class="btn btn-sm btn-secondary">✏️</a>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="post_action" value="room_delete">
                    <input type="hidden" name="record_id" value="<?= $room['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Ruimte verwijderen?')">🗑️</button>
                </form>
            </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($rooms)): ?><tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">Geen ruimtes gevonden.</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <div class="card" style="margin:0;"><div class="card-body">
        <h3 style="margin:0 0 16px;"><?= $editRoom ? '✏️ Bewerken' : '+ Toevoegen' ?></h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="post_action" value="<?= $editRoom ? 'room_update' : 'room_add' ?>">
            <?php if($editRoom): ?><input type="hidden" name="record_id" value="<?= $editRoom['id'] ?>"><?php endif; ?>
            <div class="form-group"><label>Locatie <span style="color:#ef4444;">*</span></label>
                <select name="location_id" class="form-control" required>
                    <option value="">— Kies locatie —</option>
                    <?php foreach($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= ($editRoom['location_id']??$filterLocId)==$loc['id']?'selected':'' ?>><?= htmlspecialchars($loc['org_name'].' — '.$loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php formField('Naam','name',$editRoom['name']??'','text',true); ?>
            <?php formField('Beschrijving','location_desc',$editRoom['location_desc']??''); ?>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;"><?= $editRoom ? '💾 Opslaan' : '+ Toevoegen' ?></button>
                <?php if($editRoom): ?><a href="?action=rooms<?= $filterLocId?'&loc_filter='.$filterLocId:'' ?>" class="btn btn-secondary">Annuleren</a><?php endif; ?>
            </div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
