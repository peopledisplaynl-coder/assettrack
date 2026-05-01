<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');

if (isset($_GET['deactivate'])) {
    execute("UPDATE asset_templates SET active = 0 WHERE id = ?", [(int)$_GET['deactivate']]);
    header('Location: ' . BASE_URL . '/modules/assets/templates.php?success=deactivated');
    exit;
}
if (isset($_GET['activate'])) {
    execute("UPDATE asset_templates SET active = 1 WHERE id = ?", [(int)$_GET['activate']]);
    header('Location: ' . BASE_URL . '/modules/assets/templates.php?success=activated');
    exit;
}

$templates = query(
    "SELECT t.*, u.username as created_by_name
     FROM asset_templates t
     LEFT JOIN users u ON t.created_by = u.id
     ORDER BY t.active DESC, t.name ASC"
);

// Tel assets per type
$typeCounts = [];
foreach (query("SELECT type, COUNT(*) as cnt FROM assets GROUP BY type") as $row) {
    $typeCounts[$row['type']] = $row['cnt'];
}

$pageTitle = 'Asset Templates';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Asset Templates</h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= BASE_URL ?>/modules/assets/template_edit.php" class="btn btn-primary">+ Nieuw template</a>
        <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug</a>
    </div>
</div>

<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success">
    <?= $_GET['success'] === 'saved' ? 'Template opgeslagen.' : ($_GET['success'] === 'applied' ? 'Template toegepast op assets.' : 'Klaar.') ?>
</div>
<?php endif; ?>

<div style="margin-bottom:15px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;font-size:0.9rem;color:#1e40af;">
    <strong>💡 Wat zijn templates?</strong>
    Templates zijn standaard profielen per apparaattype. Maak bijv. een template "Chromebook Acer C734" aan met standaard afschrijving, OS en fabrikant URL. Bij nieuwe assets kies je het template — alle velden worden automatisch ingevuld. Je kunt een template ook toepassen op bestaande assets.
</div>

<?php if (empty($templates)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;color:#6b7280;">
        <div style="font-size:3rem;margin-bottom:15px;">📦</div>
        <h3>Nog geen templates aangemaakt</h3>
        <p style="margin:10px 0 20px;">Maak je eerste template aan om snel nieuwe assets toe te voegen.</p>
        <a href="<?= BASE_URL ?>/modules/assets/template_edit.php" class="btn btn-primary">+ Eerste template aanmaken</a>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
    <?php foreach ($templates as $t): ?>
    <div class="card" style="margin:0;<?= !$t['active'] ? 'opacity:0.6;' : '' ?>">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:2rem;"><?= htmlspecialchars($t['icon'] ?? '📦') ?></span>
                    <div>
                        <h3 style="margin:0;font-size:1rem;color:#1a2332;"><?= htmlspecialchars($t['name']) ?></h3>
                        <?php if (!$t['active']): ?>
                        <span class="badge badge-secondary" style="font-size:0.7rem;">Inactief</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/modules/assets/template_edit.php?id=<?= $t['id'] ?>"
                   class="btn btn-sm btn-secondary">✏️ Bewerken</a>
            </div>

            <div style="display:grid;gap:4px;font-size:0.85rem;margin-bottom:12px;">
                <?php if ($t['asset_type']): ?>
                <div><span style="color:#6b7280;">Soort:</span> <strong><?= htmlspecialchars($t['asset_type']) ?></strong></div>
                <?php endif; ?>
                <?php if ($t['brand'] || $t['model']): ?>
                <div><span style="color:#6b7280;">Merk/Model:</span> <?= htmlspecialchars(trim(($t['brand'] ?? '') . ' ' . ($t['model'] ?? ''))) ?></div>
                <?php endif; ?>
                <?php if ($t['depreciation_years']): ?>
                <div><span style="color:#6b7280;">Afschrijving:</span> <?= $t['depreciation_years'] ?> jaar</div>
                <?php endif; ?>
                <?php if ($t['operating_system']): ?>
                <div><span style="color:#6b7280;">OS:</span> <?= htmlspecialchars($t['operating_system']) ?></div>
                <?php endif; ?>
                <?php if ($t['warranty_months']): ?>
                <div><span style="color:#6b7280;">Garantie:</span> <?= $t['warranty_months'] ?> maanden</div>
                <?php endif; ?>
                <?php if ($t['maintenance_interval_days']): ?>
                <div><span style="color:#6b7280;">Onderhoud:</span> elke <?= $t['maintenance_interval_days'] ?> dagen</div>
                <?php endif; ?>
            </div>

            <?php if ($t['description']): ?>
            <p style="font-size:0.82rem;color:#6b7280;margin-bottom:12px;font-style:italic;">
                <?= htmlspecialchars(mb_strimwidth($t['description'], 0, 120, '...')) ?>
            </p>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;
                        border-top:1px solid #f1f5f9;padding-top:10px;">
                <span style="font-size:0.8rem;color:#6b7280;">
                    <?= $typeCounts[$t['asset_type']] ?? 0 ?> assets van dit type
                </span>
                <div style="display:flex;gap:5px;">
                    <?php if ($t['active']): ?>
                    <a href="<?= BASE_URL ?>/modules/assets/template_sync.php?template_id=<?= $t['id'] ?>"
                       class="btn btn-sm btn-primary">▶ Toepassen</a>
                    <a href="?deactivate=<?= $t['id'] ?>"
                       class="btn btn-sm btn-secondary"
                       onclick="return confirm('Template deactiveren?')">Deactiveren</a>
                    <?php else: ?>
                    <a href="?activate=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">Activeren</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
