<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');

$id       = (int)($_GET['id'] ?? 0);
$template = $id ? queryOne("SELECT * FROM asset_templates WHERE id = ?", [$id]) : null;
$isEdit   = $template !== null;
$errors   = [];

$icons = ['💻','🖥️','🖨️','📱','⌨️','🖱️','📷','📺','🔌','📡','🔧','📦','🎮','🏫','📋','🔴'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) $errors[] = 'Naam is verplicht.';

        // Afbeelding upload verwerken
        $imageFilename = $template['image_filename'] ?? null;
        if (isset($_FILES['template_image']) && $_FILES['template_image']['error'] === UPLOAD_ERR_OK) {
            $img = $_FILES['template_image'];
            $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $errors[] = 'Ongeldig afbeeldingstype. Gebruik jpg, png, gif of webp.';
            } elseif ($img['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Afbeelding mag maximaal 5MB zijn.';
            } else {
                $uploadDir = __DIR__ . '/../../assets/uploads/template_images/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                // Verwijder oude afbeelding
                if (!empty($template['image_filename'])) {
                    $oldFile = $uploadDir . $template['image_filename'];
                    if (file_exists($oldFile)) unlink($oldFile);
                }
                $imageFilename = 'template_' . time() . '.' . $ext;
                move_uploaded_file($img['tmp_name'], $uploadDir . $imageFilename);
            }
        }

        if (empty($errors)) {
            $data = [
                'name'                     => $name,
                'description'              => trim($_POST['description'] ?? '') ?: null,
                'icon'                     => $_POST['icon'] ?? '📦',
                'asset_type'               => trim($_POST['asset_type'] ?? '') ?: null,
                'brand'                    => trim($_POST['brand'] ?? '') ?: null,
                'model'                    => trim($_POST['model'] ?? '') ?: null,
                'depreciation_years'       => (int)($_POST['depreciation_years'] ?? 0) ?: null,
                'warranty_months'          => (int)($_POST['warranty_months'] ?? 0) ?: null,
                'manufacturer_url'         => trim($_POST['manufacturer_url'] ?? '') ?: null,
                'operating_system'         => trim($_POST['operating_system'] ?? '') ?: null,
                'ram'                      => trim($_POST['ram'] ?? '') ?: null,
                'cpu'                      => trim($_POST['cpu'] ?? '') ?: null,
                'business_critical'        => isset($_POST['business_critical']) ? 1 : 0,
                'touchscreen_monitor_type' => trim($_POST['touchscreen_monitor_type'] ?? '') ?: null,
                'monitor_count'            => (int)($_POST['monitor_count'] ?? 0) ?: null,
                'maintenance_interval_days'=> (int)($_POST['maintenance_interval_days'] ?? 0) ?: null,
                'notes'                    => trim($_POST['notes'] ?? '') ?: null,
                'image_filename'           => $imageFilename,
                'active'                   => 1,
            ];

            if ($isEdit) {
                $sets   = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                $params = array_values($data);
                $params[] = $id;
                execute("UPDATE asset_templates SET $sets WHERE id = ?", $params);
            } else {
                $data['created_by'] = getUserId();
                $cols   = implode(', ', array_keys($data));
                $places = implode(', ', array_fill(0, count($data), '?'));
                execute("INSERT INTO asset_templates ($cols) VALUES ($places)", array_values($data));
            }

            header('Location: ' . BASE_URL . '/modules/assets/templates.php?success=' .
                   urlencode($isEdit ? 'Template bijgewerkt' : 'Template aangemaakt'));
            exit;
        }
    }
}

$allTypes = getAssetTypes();
$pageTitle = $isEdit ? 'Template bewerken' : 'Nieuw template';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1><?= $isEdit ? 'Template bewerken' : 'Nieuw template aanmaken' ?></h1>
    <a href="<?= BASE_URL ?>/modules/assets/templates.php" class="btn btn-secondary">← Terug</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start;">

        <!-- Links: velden -->
        <div>
            <!-- Basis -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Basis informatie
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Naam template *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($template['name'] ?? '') ?>"
                                   placeholder="bijv. Acer Chromebook school">
                        </div>
                        <div class="form-group">
                            <label>Apparaattype</label>
                            <select name="asset_type" class="form-control">
                                <option value="">-- Kies type --</option>
                                <?php foreach ($allTypes as $t): ?>
                                <option value="<?= htmlspecialchars($t['name']) ?>"
                                    <?= ($template['asset_type'] ?? '') === $t['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Beschrijving</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Korte omschrijving"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Merk</label>
                            <input type="text" name="brand" class="form-control"
                                   value="<?= htmlspecialchars($template['brand'] ?? '') ?>"
                                   placeholder="bijv. Acer, HP, Dell">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" class="form-control"
                                   value="<?= htmlspecialchars($template['model'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Fabrikant URL</label>
                        <input type="text" name="manufacturer_url" class="form-control" style="width:100%;"
                               value="<?= htmlspecialchars($template['manufacturer_url'] ?? '') ?>"
                               placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="business_critical" value="1"
                                   <?= ($template['business_critical'] ?? 0) ? 'checked' : '' ?>>
                            Bedrijfskritisch
                        </label>
                    </div>
                </div>
            </div>

            <!-- Hardware -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Hardware specificaties
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Besturingssysteem</label>
                            <input type="text" name="operating_system" class="form-control"
                                   value="<?= htmlspecialchars($template['operating_system'] ?? '') ?>"
                                   placeholder="bijv. ChromeOS, Windows 11">
                        </div>
                        <div class="form-group">
                            <label>RAM</label>
                            <input type="text" name="ram" class="form-control"
                                   value="<?= htmlspecialchars($template['ram'] ?? '') ?>"
                                   placeholder="bijv. 8GB">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CPU</label>
                            <input type="text" name="cpu" class="form-control"
                                   value="<?= htmlspecialchars($template['cpu'] ?? '') ?>"
                                   placeholder="bijv. Intel Celeron N4500">
                        </div>
                        <div class="form-group">
                            <label>Monitor type</label>
                            <input type="text" name="touchscreen_monitor_type" class="form-control"
                                   value="<?= htmlspecialchars($template['touchscreen_monitor_type'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Aantal monitoren</label>
                        <input type="number" name="monitor_count" class="form-control"
                               value="<?= htmlspecialchars($template['monitor_count'] ?? '') ?>"
                               min="0" style="width:100px;">
                    </div>
                </div>
            </div>

            <!-- Financieel -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Financieel & Onderhoud
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Afschrijving (jaren)</label>
                            <input type="number" name="depreciation_years" class="form-control"
                                   value="<?= htmlspecialchars($template['depreciation_years'] ?? '') ?>"
                                   min="1" max="20" placeholder="bijv. 5">
                        </div>
                        <div class="form-group">
                            <label>Garantieperiode (maanden)</label>
                            <input type="number" name="warranty_months" class="form-control"
                                   value="<?= htmlspecialchars($template['warranty_months'] ?? '') ?>"
                                   min="1" placeholder="bijv. 36">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Onderhoudsinterval (dagen)</label>
                        <input type="number" name="maintenance_interval_days" class="form-control"
                               value="<?= htmlspecialchars($template['maintenance_interval_days'] ?? '') ?>"
                               min="1" placeholder="bijv. 365" style="width:150px;">
                    </div>
                </div>
            </div>

            <!-- Notities -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Standaard notitie
                    </h3>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="Standaard opmerking voor assets van dit type"><?= htmlspecialchars($template['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Rechts: icoon, afbeelding, opslaan -->
        <div style="position:sticky;top:70px;">

            <!-- Icoon -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Icoon kiezen
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                        <?php foreach ($icons as $icon): ?>
                        <?php $checked = ($template['icon'] ?? '📦') === $icon; ?>
                        <label style="cursor:pointer;text-align:center;">
                            <input type="radio" name="icon" value="<?= htmlspecialchars($icon) ?>"
                                   <?= $checked ? 'checked' : '' ?>
                                   style="display:none;">
                            <span style="
                                display:block;font-size:1.6rem;padding:6px;
                                border-radius:8px;
                                border:2px solid <?= $checked ? '#2563eb' : 'transparent' ?>;
                                background:<?= $checked ? '#eff6ff' : 'transparent' ?>;
                                cursor:pointer;
                                transition:all 0.15s;
                            " onclick="selectIcon(this, '<?= htmlspecialchars($icon) ?>')">
                                <?= $icon ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Afbeelding -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Standaard afbeelding
                    </h3>
                    <p style="color:#6b7280;font-size:0.8rem;margin-bottom:12px;">
                        Wordt getoond bij assets van dit type zonder eigen foto.
                    </p>
                    <?php if (!empty($template['image_filename'])): ?>
                    <div style="margin-bottom:12px;">
                        <img src="<?= BASE_URL ?>/assets/uploads/template_images/<?= htmlspecialchars($template['image_filename']) ?>"
                             style="width:100%;max-height:140px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">
                        <p style="font-size:0.75rem;color:#6b7280;margin-top:4px;">
                            Huidige afbeelding — upload nieuwe om te vervangen
                        </p>
                    </div>
                    <?php endif; ?>
                    <div class="form-group" style="margin:0;">
                        <input type="file" name="template_image"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               class="form-control">
                        <small style="color:#6b7280;">Max 5MB. JPG, PNG, GIF of WEBP.</small>
                    </div>
                </div>
            </div>

            <!-- Opslaan -->
            <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-size:1rem;">
                <?= $isEdit ? '💾 Wijzigingen opslaan' : '✅ Template aanmaken' ?>
            </button>
        </div>
    </div>
</form>

<script>
function selectIcon(el, icon) {
    // Reset alle iconen
    document.querySelectorAll('[name="icon"] + span').forEach(s => {
        s.style.borderColor = 'transparent';
        s.style.background = 'transparent';
    });
    // Markeer geselecteerde
    el.style.borderColor = '#2563eb';
    el.style.background = '#eff6ff';
    // Vink de bijbehorende radio aan
    el.previousElementSibling.checked = true;
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
