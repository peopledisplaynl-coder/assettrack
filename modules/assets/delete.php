<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('delete_assets');

$id    = (int)($_GET['id'] ?? 0);
$asset = getAssetById($id);

if (!$asset) {
    header('Location: ' . BASE_URL . '/modules/assets/?error=Asset+niet+gevonden');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige aanvraag.';
    } elseif (($_POST['confirm'] ?? '') === 'DELETE') {
        try {
            $oldValues = $asset;
            deleteAsset($id);
            logAudit('DELETE', 'assets', $id, $oldValues, null);
            header('Location: ' . BASE_URL . '/modules/assets/?success=Asset+verwijderd');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Fout bij verwijderen: ' . $e->getMessage();
        }
    } else {
        $errors[] = 'Typ DELETE om te bevestigen.';
    }
}

$pageTitle = 'Asset verwijderen';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Asset verwijderen</h1>
    <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">← Terug</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-danger">
            ⚠️ <strong>Let op:</strong> Deze actie kan niet ongedaan worden gemaakt.<br>
            Asset <strong><?= htmlspecialchars($asset['asset_number']) ?></strong>
            (<?= htmlspecialchars($asset['brand'] . ' ' . $asset['model']) ?>) wordt permanent verwijderd.
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="form-group">
                <label>Typ <strong>DELETE</strong> om te bevestigen:</label>
                <input type="text" name="confirm" class="form-control"
                       placeholder="DELETE" style="max-width:200px;" required>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-danger">Definitief verwijderen</button>
                <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
