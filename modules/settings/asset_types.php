<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_settings');

$errors  = [];
$success = '';
$tab     = $_GET['tab'] ?? 'types';

// ─── Asset Types ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_type') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $name = trim($_POST['type_name'] ?? '');
        if (empty($name)) {
            $errors[] = 'Naam is verplicht.';
        } else {
            try {
                execute("INSERT INTO asset_types (name, active) VALUES (?, 1)", [$name]);
                $success = "Soort '$name' toegevoegd.";
            } catch (Exception $e) {
                $errors[] = "Soort bestaat al of kon niet worden toegevoegd.";
            }
        }
    }
}

if (isset($_GET['toggle_type'])) {
    $tid = (int)$_GET['toggle_type'];
    $t = queryOne("SELECT active FROM asset_types WHERE id = ?", [$tid]);
    if ($t) {
        execute("UPDATE asset_types SET active = ? WHERE id = ?", [$t['active'] ? 0 : 1, $tid]);
        header('Location: ?tab=types&success=' . urlencode('Status gewijzigd'));
        exit;
    }
}

if (isset($_GET['delete_type'])) {
    $tid = (int)$_GET['delete_type'];
    $used = queryOne("SELECT COUNT(*) as n FROM assets WHERE type = (SELECT name FROM asset_types WHERE id = ?)", [$tid]);
    if ($used['n'] > 0) {
        $errors[] = "Kan niet verwijderen — er zijn {$used['n']} assets van dit type.";
    } else {
        execute("DELETE FROM asset_types WHERE id = ?", [$tid]);
        header('Location: ?tab=types&success=Type+verwijderd');
        exit;
    }
}

// Bewerk type naam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'rename_type') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $tid  = (int)$_POST['type_id'];
        $name = trim($_POST['type_name'] ?? '');
        $old  = queryOne("SELECT name FROM asset_types WHERE id = ?", [$tid]);
        if ($old && $name) {
            // Update ook bestaande assets
            execute("UPDATE assets SET type = ? WHERE type = ?", [$name, $old['name']]);
            execute("UPDATE asset_types SET name = ? WHERE id = ?", [$name, $tid]);
            $success = "Type hernoemd van '{$old['name']}' naar '$name'. Assets bijgewerkt.";
        }
    }
}

if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);

$types = query("SELECT t.*, COUNT(a.id) as asset_count
    FROM asset_types t
    LEFT JOIN assets a ON a.type = t.name
    GROUP BY t.id ORDER BY t.name");

$pageTitle = 'Asset soorten beheren';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>⚙️ Asset soorten beheren</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug naar instellingen</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

    <!-- Lijst -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                Huidige soorten
            </h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>Aantal assets</th>
                        <th>Status</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $t): ?>
                    <tr style="<?= !$t['active'] ? 'opacity:0.5;' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($t['name']) ?></strong>
                        </td>
                        <td><?= $t['asset_count'] ?></td>
                        <td>
                            <span class="badge <?= $t['active'] ? 'badge-success' : 'badge-secondary' ?>">
                                <?= $t['active'] ? 'Actief' : 'Inactief' ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:6px;">
                            <button onclick="showRename(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')"
                                    class="btn btn-sm btn-secondary">✏️ Hernoemen</button>
                            <a href="?toggle_type=<?= $t['id'] ?>&tab=types"
                               class="btn btn-sm btn-secondary">
                                <?= $t['active'] ? 'Deactiveren' : 'Activeren' ?>
                            </a>
                            <?php if ($t['asset_count'] == 0): ?>
                            <a href="?delete_type=<?= $t['id'] ?>&tab=types"
                               onclick="return confirm('Type definitief verwijderen?')"
                               class="btn btn-sm btn-danger">✕</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulieren -->
    <div>
        <!-- Nieuw type toevoegen -->
        <div class="card" style="margin-bottom:15px;">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;">+ Nieuw soort toevoegen</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="form" value="add_type">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Naam</label>
                        <input type="text" name="type_name" class="form-control" required
                               placeholder="bijv. Chromebook, Tablet, Beamer">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Toevoegen</button>
                </form>
            </div>
        </div>

        <!-- Hernoem formulier (verborgen) -->
        <div class="card" id="renameCard" style="display:none;">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;">✏️ Soort hernoemen</h3>
                <p style="font-size:0.875rem;color:#6b7280;margin-bottom:10px;">
                    Alle bestaande assets worden automatisch bijgewerkt naar de nieuwe naam.
                </p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="form" value="rename_type">
                    <input type="hidden" name="type_id" id="renameId">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Huidige naam</label>
                        <input type="text" id="renameOld" class="form-control" disabled>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Nieuwe naam *</label>
                        <input type="text" name="type_name" id="renameNew" class="form-control" required>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">Opslaan</button>
                        <button type="button" onclick="hideRename()" class="btn btn-secondary">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info -->
        <div class="card">
            <div class="card-body">
                <h4 style="margin:0 0 8px;color:#1a2332;font-size:0.9rem;">💡 Tips</h4>
                <ul style="font-size:0.8rem;color:#6b7280;padding-left:15px;line-height:1.8;">
                    <li>Hernoemen werkt ook op alle bestaande assets</li>
                    <li>Deactiveren verbergt het type in dropdowns maar behoudt bestaande assets</li>
                    <li>Verwijderen is alleen mogelijk als er geen assets aan gekoppeld zijn</li>
                    <li>Voeg soorten toe die passen bij jouw organisatie (Chromebook, Beamer, Camera, etc.)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function showRename(id, name) {
    document.getElementById('renameId').value  = id;
    document.getElementById('renameOld').value = name;
    document.getElementById('renameNew').value = name;
    document.getElementById('renameCard').style.display = 'block';
    document.getElementById('renameNew').focus();
    document.getElementById('renameCard').scrollIntoView({behavior:'smooth'});
}
function hideRename() {
    document.getElementById('renameCard').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
