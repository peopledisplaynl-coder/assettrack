<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_kb');

$errors  = [];
$success = '';
$edit    = (int)($_GET['edit'] ?? 0);
$editCat = $edit ? queryOne("SELECT * FROM kb_categories WHERE id = ?", [$edit]) : null;

// Verwijder categorie
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $count = queryOne("SELECT COUNT(*) as n FROM kb_articles WHERE category_id = ?", [$did]);
    if ($count['n'] > 0) {
        $errors[] = 'Kan categorie niet verwijderen — er zijn nog ' . $count['n'] . ' artikelen aan gekoppeld.';
    } else {
        execute("DELETE FROM kb_categories WHERE id = ?", [$did]);
        header('Location: ' . BASE_URL . '/modules/kb/categories.php?success=Categorie+verwijderd');
        exit;
    }
}

if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);

// Opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $name  = trim($_POST['name'] ?? '');
        $icon  = $_POST['icon'] ?? '📄';
        $icon  = mb_substr(trim($icon), 0, 10); // Veilige emoji opslag
        $desc  = trim($_POST['description'] ?? '') ?: null;
        $sort  = (int)($_POST['sort_order'] ?? 0);
        $catId = (int)($_POST['cat_id'] ?? 0);

        if (empty($name)) $errors[] = 'Naam is verplicht.';

        if (empty($errors)) {
            if ($catId) {
                execute("UPDATE kb_categories SET name=?,icon=?,description=?,sort_order=? WHERE id=?",
                    [$name,$icon,$desc,$sort,$catId]);
            } else {
                execute("INSERT INTO kb_categories (name,icon,description,sort_order,active) VALUES (?,?,?,?,1)",
                    [$name,$icon,$desc,$sort]);
            }
            header('Location: ' . BASE_URL . '/modules/kb/categories.php?success=' .
                urlencode($catId ? 'Categorie bijgewerkt' : 'Categorie aangemaakt'));
            exit;
        }
    }
}

$categories = query("SELECT c.*, COUNT(a.id) as article_count
    FROM kb_categories c
    LEFT JOIN kb_articles a ON a.category_id = c.id AND a.active = 1
    WHERE c.active = 1
    GROUP BY c.id ORDER BY c.sort_order, c.name");

$icons = ['📋','💻','🖥️','🌐','🔧','📱','🖨️','🔌','📡','⚙️','📚','❓','🔒','💡','📝'];

$pageTitle = 'Kennisbank Categorieën';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Kennisbank — Categorieën</h1>
    <a href="<?= BASE_URL ?>/modules/kb/" class="btn btn-secondary">← Terug naar kennisbank</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

    <!-- Categorie lijst -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;">Bestaande categorieën</h3>
            <?php if (empty($categories)): ?>
            <p style="color:#6b7280;">Nog geen categorieën. Maak er één aan →</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Icoon</th><th>Naam</th><th>Artikelen</th><th>Volgorde</th><th>Acties</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-size:1.3rem;"><?= $cat['icon'] ?? '📄' ?></td>
                        <td>
                            <strong><?= htmlspecialchars($cat['name']) ?></strong>
                            <?php if ($cat['description']): ?>
                            <div style="font-size:0.8rem;color:#6b7280;"><?= htmlspecialchars($cat['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $cat['article_count'] ?></td>
                        <td><?= $cat['sort_order'] ?></td>
                        <td style="display:flex;gap:6px;">
                            <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-secondary">✏️</a>
                            <?php if ($cat['article_count'] == 0): ?>
                            <a href="?delete=<?= $cat['id'] ?>"
                               onclick="return confirm('Categorie verwijderen?')"
                               class="btn btn-sm btn-danger">✕</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulier -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;">
                <?= $editCat ? 'Categorie bewerken' : 'Nieuwe categorie' ?>
            </h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="cat_id" value="<?= $editCat['id'] ?? 0 ?>">

                <div class="form-group">
                    <label>Icoon</label>
                    <?php
                    $iconLabels = [
                        '📋'=>'Algemeen','💻'=>'Laptop/PC','🖥️'=>'Monitor','🌐'=>'Netwerk',
                        '🔧'=>'Hardware','📱'=>'Mobiel','🖨️'=>'Printer','🔌'=>'Randapparaten',
                        '📡'=>'Verbinding','⚙️'=>'Systeem','📚'=>'Software','❓'=>'Overig',
                        '🔒'=>'Beveiliging','💡'=>'Tips','📝'=>'Procedures'
                    ]; ?>
                    <select name="icon" id="iconSelect" class="form-control">
                        <?php foreach ($icons as $ic): ?>
                        <option value="<?= $ic ?>" <?= ($editCat['icon'] ?? '📋') === $ic ? 'selected' : '' ?>>
                            <?= $ic ?> &mdash; <?= htmlspecialchars($iconLabels[$ic] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top:6px;font-size:0.8rem;color:#6b7280;">
                        Voorbeeld: <span id="iconPreview" style="font-size:1.5rem;"><?= $editCat['icon'] ?? '📋' ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Naam *</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= htmlspecialchars($editCat['name'] ?? '') ?>"
                           placeholder="bijv. Hardware, Software, Netwerk">
                </div>
                <div class="form-group">
                    <label>Beschrijving</label>
                    <input type="text" name="description" class="form-control"
                           value="<?= htmlspecialchars($editCat['description'] ?? '') ?>"
                           placeholder="Korte omschrijving (optioneel)">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Sorteervolgorde</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="<?= $editCat['sort_order'] ?? 0 ?>"
                           min="0" style="width:80px;">
                </div>
                <div style="display:flex;gap:8px;margin-top:15px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">
                        <?= $editCat ? '💾 Opslaan' : '+ Aanmaken' ?>
                    </button>
                    <?php if ($editCat): ?>
                    <a href="<?= BASE_URL ?>/modules/kb/categories.php" class="btn btn-secondary">Annuleren</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectIcon(el) {} // niet meer gebruikt
document.querySelector('select[name="icon"]')?.addEventListener('change', function() {
    const preview = document.getElementById('iconPreview');
    if (preview) preview.textContent = this.value;
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
