<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_settings');

$success = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add' || $postAction === 'edit') {
        $label    = trim($_POST['field_label'] ?? '');
        $type     = $_POST['field_type'] ?? 'text';
        $required = isset($_POST['required']) ? 1 : 0;
        $active   = isset($_POST['active']) ? 1 : 0;
        $options  = trim($_POST['field_options'] ?? '');
        $validTypes = ['text','number','date','select','boolean','textarea','ip','mac'];

        if (!$label) $errors[] = 'Veldnaam is verplicht.';
        if (!in_array($type, $validTypes)) $errors[] = 'Ongeldig veldtype.';

        // Zet opties om naar JSON
        $optionsJson = null;
        if ($type === 'select' && $options) {
            $opts = array_filter(array_map('trim', explode("\n", $options)));
            $optionsJson = json_encode(array_values($opts));
        }

        if (empty($errors)) {
            $fieldName = strtolower(preg_replace('/[^a-z0-9]/i', '_', $label));
            if ($postAction === 'add') {
                $maxOrder = queryOne("SELECT MAX(sort_order) as m FROM custom_fields")['m'] ?? 0;
                execute("INSERT INTO custom_fields (field_name, field_label, field_type, field_options, required, active, sort_order) VALUES (?,?,?,?,?,?,?)",
                    [$fieldName, $label, $type, $optionsJson, $required, $active, $maxOrder + 1]);
                $success = 'Veld toegevoegd.';
            } else {
                $id = (int)$_POST['id'];
                execute("UPDATE custom_fields SET field_label=?, field_type=?, field_options=?, required=?, active=? WHERE id=?",
                    [$label, $type, $optionsJson, $required, $active, $id]);
                $success = 'Veld bijgewerkt.';
            }
        }
    } elseif ($postAction === 'toggle') {
        $id   = (int)$_POST['id'];
        $curr = queryOne("SELECT active FROM custom_fields WHERE id=?", [$id]);
        execute("UPDATE custom_fields SET active=? WHERE id=?", [$curr['active'] ? 0 : 1, $id]);
        $success = 'Status gewijzigd.';
    } elseif ($postAction === 'move') {
        $id        = (int)$_POST['id'];
        $direction = $_POST['direction'] ?? '';
        $field     = queryOne("SELECT * FROM custom_fields WHERE id=?", [$id]);
        if ($field) {
            if ($direction === 'up') {
                $swap = queryOne("SELECT * FROM custom_fields WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1", [$field['sort_order']]);
            } else {
                $swap = queryOne("SELECT * FROM custom_fields WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1", [$field['sort_order']]);
            }
            if ($swap) {
                execute("UPDATE custom_fields SET sort_order=? WHERE id=?", [$swap['sort_order'], $id]);
                execute("UPDATE custom_fields SET sort_order=? WHERE id=?", [$field['sort_order'], $swap['id']]);
            }
        }
    }
}

$fields  = query("SELECT * FROM custom_fields ORDER BY sort_order, id");
$editId  = (int)($_GET['edit'] ?? 0);
$editItem = $editId ? queryOne("SELECT * FROM custom_fields WHERE id=?", [$editId]) : null;

$typeLabels = [
    'text'     => 'Tekst',
    'number'   => 'Nummer',
    'date'     => 'Datum',
    'select'   => 'Keuzelijst',
    'boolean'  => 'Ja/Nee',
    'textarea' => 'Tekstvak',
    'ip'       => 'IP-adres',
    'mac'      => 'MAC-adres',
];

$pageTitle = 'Custom velden';
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header">
    <h1>Custom velden</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors):  ?><div class="alert alert-danger"><ul style="margin:0;padding-left:20px;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 350px;gap:20px;align-items:start;">
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;">Bestaande velden</h3>
            <p style="color:#6b7280;font-size:0.875rem;margin-bottom:15px;">Custom velden verschijnen automatisch in het asset-formulier.</p>
            <table class="data-table">
                <thead>
                    <tr><th>Volgorde</th><th>Label</th><th>Type</th><th>Verplicht</th><th>Status</th><th>Acties</th></tr>
                </thead>
                <tbody>
                <?php if (empty($fields)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;">Nog geen custom velden.</td></tr>
                <?php else: ?>
                <?php foreach ($fields as $field): ?>
                <tr>
                    <td>
                        <form method="POST" style="display:flex;gap:4px;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= $field['id'] ?>">
                            <button name="direction" value="up" class="btn btn-sm btn-secondary" title="Omhoog">▲</button>
                            <button name="direction" value="down" class="btn btn-sm btn-secondary" title="Omlaag">▼</button>
                        </form>
                    </td>
                    <td><?= htmlspecialchars($field['field_label']) ?></td>
                    <td><?= $typeLabels[$field['field_type']] ?? $field['field_type'] ?></td>
                    <td><?= $field['required'] ? 'Ja' : 'Nee' ?></td>
                    <td><span class="badge <?= $field['active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $field['active'] ? 'Actief' : 'Inactief' ?></span></td>
                    <td style="display:flex;gap:4px;">
                        <a href="?edit=<?= $field['id'] ?>" class="btn btn-sm btn-secondary">Bewerken</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $field['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $field['active'] ? 'btn-warning' : 'btn-success' ?>"><?= $field['active'] ? 'Deactiveren' : 'Activeren' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;"><?= $editItem ? 'Veld bewerken' : 'Veld toevoegen' ?></h3>
            <form method="POST" id="fieldForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label>Veldnaam (label) *</label>
                    <input type="text" name="field_label" class="form-control" value="<?= htmlspecialchars($editItem['field_label'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Veldtype *</label>
                    <select name="field_type" class="form-control" id="fieldType" onchange="toggleOptions()">
                        <?php foreach ($typeLabels as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($editItem['field_type'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="optionsGroup" style="display:none;">
                    <label>Opties (één per regel)</label>
                    <textarea name="field_options" class="form-control" rows="4" placeholder="Optie 1&#10;Optie 2&#10;Optie 3"><?php
                        if ($editItem && $editItem['field_options']) {
                            $opts = json_decode($editItem['field_options'], true);
                            echo htmlspecialchars(implode("\n", $opts ?? []));
                        }
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="required" value="1" <?= ($editItem['required'] ?? 0) ? 'checked' : '' ?>> Verplicht veld</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="active" value="1" <?= ($editItem['active'] ?? 1) ? 'checked' : '' ?>> Actief</label>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><?= $editItem ? 'Opslaan' : 'Toevoegen' ?></button>
                    <?php if ($editItem): ?><a href="<?= BASE_URL ?>/modules/settings/custom_fields.php" class="btn btn-secondary">Annuleren</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function toggleOptions() {
    const type = document.getElementById('fieldType').value;
    document.getElementById('optionsGroup').style.display = type === 'select' ? 'block' : 'none';
}
toggleOptions();
</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
