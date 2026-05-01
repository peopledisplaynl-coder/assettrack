<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_settings');

$success = '';
$errors  = [];
$section = $_GET['section'] ?? 'brands';
$editId  = (int)($_GET['edit'] ?? 0);

$brands = query("SELECT * FROM brands ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';
    $name       = trim($_POST['name'] ?? '');
    $id         = (int)($_POST['id'] ?? 0);

    if ($postAction === 'add') {
        if (!$name) {
            $errors[] = 'Naam is verplicht.';
        } else {
            try {
                execute("INSERT INTO brands (name, active, use_count) VALUES (?, 1, 0)", [$name]);
                $success = "Merk '$name' toegevoegd.";
            } catch (Exception $e) {
                $errors[] = 'Merk bestaat al of kon niet worden toegevoegd.';
            }
        }
    }

    if ($postAction === 'edit') {
        if (!$name) {
            $errors[] = 'Naam is verplicht.';
        } else {
            try {
                execute("UPDATE brands SET name=? WHERE id=?", [$name, $id]);
                $success = "Merk bijgewerkt.";
                $editId = 0;
            } catch (Exception $e) {
                $errors[] = 'Kon niet opslaan: ' . $e->getMessage();
            }
        }
    }

    if ($postAction === 'toggle') {
        $curr = queryOne("SELECT active FROM brands WHERE id=?", [$id]);
        if ($curr) {
            execute("UPDATE brands SET active=? WHERE id=?", [$curr['active'] ? 0 : 1, $id]);
            $success = 'Status gewijzigd.';
        }
    }

    $brands = query("SELECT * FROM brands ORDER BY name ASC");
}

$editItem = $editId ? queryOne("SELECT * FROM brands WHERE id=?", [$editId]) : null;

$pageTitle = 'Merken';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>🏷️ Merken</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Snelle links naar verwante pagina's -->
<div style="display:flex;gap:8px;margin-bottom:20px;">
    <a href="<?= BASE_URL ?>/modules/settings/asset_types.php"
       class="btn btn-secondary">📋 Asset soorten beheren →</a>
    <a href="<?= BASE_URL ?>/modules/settings/locations.php?action=rooms"
       class="btn btn-secondary">🚪 Ruimtes beheren →</a>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;">Merken (<?= count($brands) ?>)</h3>
            <table class="data-table">
                <thead>
                    <tr><th>Naam</th><th>Gebruik</th><th>Status</th><th>Acties</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $item): ?>
                    <tr style="<?= !$item['active'] ? 'opacity:0.5;' : '' ?>">
                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                        <td><?= (int)($item['use_count'] ?? 0) ?> assets</td>
                        <td>
                            <span class="badge <?= $item['active'] ? 'badge-success' : 'badge-secondary' ?>">
                                <?= $item['active'] ? 'Actief' : 'Inactief' ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:6px;">
                            <a href="?section=brands&edit=<?= $item['id'] ?>"
                               class="btn btn-sm btn-secondary">✏️</a>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $item['active'] ? 'btn-warning' : 'btn-success' ?>">
                                    <?= $item['active'] ? 'Deactiveren' : 'Activeren' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($brands)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#6b7280;">Nog geen merken.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;">
                <?= $editItem ? '✏️ Merk bewerken' : '+ Merk toevoegen' ?>
            </h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Merknaam *</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"
                           placeholder="bijv. Dell, HP, Acer">
                </div>
                <div style="display:flex;gap:8px;margin-top:15px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">
                        <?= $editItem ? '💾 Opslaan' : '+ Toevoegen' ?>
                    </button>
                    <?php if ($editItem): ?>
                    <a href="?section=brands" class="btn btn-secondary">Annuleren</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
