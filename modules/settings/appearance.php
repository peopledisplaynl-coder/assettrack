<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_appearance');

$errors  = [];
$success = '';

$company = queryOne("SELECT * FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
$companyId = $company['id'] ?? 1;

// ── POST: thema opslaan ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $form = $_POST['form'] ?? '';

    if ($form === 'theme') {
        $appName   = trim($_POST['app_name']   ?? 'AssetTrack');
        $primary   = trim($_POST['theme_primary']   ?? '#2563eb');
        $secondary = trim($_POST['theme_secondary'] ?? '#1a2332');
        $accent    = trim($_POST['theme_accent']    ?? '#3b82f6');
        $font      = trim($_POST['font_family']     ?? 'DM Sans');

        // Valideer hex kleuren
        $hexPattern = '/^#[0-9a-fA-F]{6}$/';
        if (!preg_match($hexPattern, $primary))   $primary   = '#2563eb';
        if (!preg_match($hexPattern, $secondary)) $secondary = '#1a2332';
        if (!preg_match($hexPattern, $accent))    $accent    = '#3b82f6';
        $font = preg_replace('/[^a-zA-Z0-9 ,]/', '', $font);

        // Logo upload
        $logoPath = $company['app_logo'] ?? null;
        if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
            $img = $_FILES['app_logo'];
            $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                $filename = 'app_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($img['tmp_name'], $uploadDir . $filename)) {
                    $logoPath = BASE_URL . '/assets/uploads/' . $filename;
                }
            }
        }
        if (isset($_POST['remove_logo'])) $logoPath = null;

        execute("UPDATE companies SET app_name=?,theme_primary=?,theme_secondary=?,
                 theme_accent=?,font_family=?,app_logo=? WHERE id=?",
            [$appName, $primary, $secondary, $accent, $font, $logoPath, $companyId]);

        $company = queryOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
        $success = 'Thema instellingen opgeslagen.';
    }

    if ($form === 'location_color') {
        $locId = (int)($_POST['location_id'] ?? 0);
        $color = trim($_POST['theme_color']  ?? '');
        if (empty($color)) $color = null;
        elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = null;

        execute("UPDATE locations SET theme_color = ? WHERE id = ?", [$color, $locId]);
        $success = 'Locatie kleur opgeslagen.';
    }
}

$locations = query("SELECT l.*, o.name as org_name FROM locations l
                    JOIN organisations o ON l.organisation_id = o.id
                    WHERE l.active = 1 ORDER BY o.name, l.name");

$fonts = [
    'DM Sans'        => 'DM Sans — Modern, helder',
    'Inter'          => 'Inter — Neutraal, zakelijk',
    'Plus Jakarta Sans' => 'Plus Jakarta Sans — Vriendelijk, modern',
    'Outfit'         => 'Outfit — Strak, geometrisch',
    'Nunito'         => 'Nunito — Afgerond, leesbaar',
    'Source Sans 3'  => 'Source Sans 3 — Professioneel',
    'Figtree'        => 'Figtree — Schoon, eigentijds',
    'Geist'          => 'Geist — Technisch, minimaal',
];

$pageTitle = 'Weergave & Thema';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>🎨 Weergave & Thema</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Thema instellingen -->
    <div>
        <div class="card">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    🏷️ Naam & Logo
                </h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="form" value="theme">

                    <div class="form-group">
                        <label>Applicatienaam</label>
                        <input type="text" name="app_name" class="form-control"
                               value="<?= htmlspecialchars($company['app_name'] ?? 'AssetTrack') ?>"
                               placeholder="AssetTrack">
                        <small style="color:#6b7280;">Wordt getoond in de header en browser tab.</small>
                    </div>

                    <div class="form-group">
                        <label>Logo (optioneel)</label>
                        <?php if (!empty($company['app_logo'])): ?>
                        <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px;">
                            <img src="<?= htmlspecialchars($company['app_logo']) ?>"
                                 style="max-height:40px;max-width:150px;object-fit:contain;
                                        border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">
                                <input type="checkbox" name="remove_logo" value="1">
                                <span style="font-size:0.85rem;color:#ef4444;">Logo verwijderen</span>
                            </label>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="app_logo" class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                        <small style="color:#6b7280;">Max 2MB. PNG met transparante achtergrond werkt het mooist (max 200×60px).</small>
                    </div>

                    <h3 style="color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;margin:20px 0 15px;">
                        🎨 Kleuren
                    </h3>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div class="form-group" style="margin:0;">
                            <label>Primaire kleur</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="color" name="theme_primary" class="form-control"
                                       style="padding:2px;height:38px;width:50px;cursor:pointer;"
                                       value="<?= htmlspecialchars($company['theme_primary'] ?? '#2563eb') ?>">
                                <input type="text" id="primary_text"
                                       style="flex:1;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:0.82rem;"
                                       value="<?= htmlspecialchars($company['theme_primary'] ?? '#2563eb') ?>"
                                       oninput="syncColor('theme_primary',this.value)">
                            </div>
                            <small style="color:#6b7280;">Knoppen, actieve menu items</small>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Header kleur</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="color" name="theme_secondary" class="form-control"
                                       style="padding:2px;height:38px;width:50px;cursor:pointer;"
                                       value="<?= htmlspecialchars($company['theme_secondary'] ?? '#1a2332') ?>">
                                <input type="text" id="secondary_text"
                                       style="flex:1;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:0.82rem;"
                                       value="<?= htmlspecialchars($company['theme_secondary'] ?? '#1a2332') ?>"
                                       oninput="syncColor('theme_secondary',this.value)">
                            </div>
                            <small style="color:#6b7280;">Achtergrond header/navigatie</small>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Accent kleur</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="color" name="theme_accent" class="form-control"
                                       style="padding:2px;height:38px;width:50px;cursor:pointer;"
                                       value="<?= htmlspecialchars($company['theme_accent'] ?? '#3b82f6') ?>">
                                <input type="text" id="accent_text"
                                       style="flex:1;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:0.82rem;"
                                       value="<?= htmlspecialchars($company['theme_accent'] ?? '#3b82f6') ?>"
                                       oninput="syncColor('theme_accent',this.value)">
                            </div>
                            <small style="color:#6b7280;">Badges, highlights</small>
                        </div>
                    </div>

                    <!-- Snelkeuze kleurenpalet -->
                    <div style="margin-bottom:16px;">
                        <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:6px;">Snelkeuze paletten</label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php
                            $palettes = [
                                ['Blauw (standaard)', '#2563eb', '#1a2332', '#3b82f6'],
                                ['Groen', '#059669', '#1a2d27', '#10b981'],
                                ['Paars', '#7c3aed', '#1e1a2e', '#8b5cf6'],
                                ['Rood', '#dc2626', '#2d1a1a', '#ef4444'],
                                ['Oranje', '#ea580c', '#2d1f0d', '#f97316'],
                                ['Teal', '#0891b2', '#0c1f2d', '#06b6d4'],
                                ['Slate', '#475569', '#1e2330', '#64748b'],
                                ['Rose', '#e11d48', '#2d1a20', '#f43f5e'],
                            ];
                            foreach ($palettes as [$name, $p, $s, $a]):
                            ?>
                            <button type="button"
                                    onclick="applyPalette('<?= $p ?>', '<?= $s ?>', '<?= $a ?>')"
                                    style="display:flex;align-items:center;gap:5px;padding:5px 10px;
                                           border:1.5px solid #e5e7eb;border-radius:20px;font-size:0.78rem;
                                           background:white;cursor:pointer;transition:all 0.15s;"
                                    onmouseover="this.style.borderColor='<?= $p ?>'"
                                    onmouseout="this.style.borderColor='#e5e7eb'">
                                <span style="display:flex;gap:2px;">
                                    <span style="width:12px;height:12px;border-radius:50%;background:<?= $p ?>;"></span>
                                    <span style="width:12px;height:12px;border-radius:50%;background:<?= $s ?>;"></span>
                                </span>
                                <?= $name ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <h3 style="color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;margin:20px 0 15px;">
                        🔤 Lettertype
                    </h3>

                    <div class="form-group">
                        <label>Lettertype</label>
                        <select name="font_family" class="form-control" id="fontSelect"
                                onchange="previewFont(this.value)">
                            <?php foreach ($fonts as $fval => $flabel): ?>
                            <option value="<?= htmlspecialchars($fval) ?>"
                                <?= ($company['font_family'] ?? 'DM Sans') === $fval ? 'selected' : '' ?>>
                                <?= htmlspecialchars($flabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="fontPreview"
                             style="margin-top:8px;padding:10px 14px;background:#f8fafc;
                                    border-radius:6px;border:1px solid #e5e7eb;font-size:1rem;">
                            De snelle bruine vos springt over de luie hond. 0123456789
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:5px;">
                        💾 Thema opslaan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Locatie kleuren + preview -->
    <div>
        <!-- Locatie kleuren -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    📍 Kleur per locatie
                </h3>
                <p style="color:#6b7280;font-size:0.875rem;margin-bottom:15px;">
                    Geef elke locatie een eigen kleur. Deze kleur verschijnt in de locatie badge, 
                    de accent balk en de navigatie als die locatie actief is.
                </p>
                <?php foreach ($locations as $loc): ?>
                <form method="POST" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="form" value="location_color">
                    <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                    <div style="display:flex;align-items:center;gap:8px;flex:1;">
                        <?php if (!empty($loc['logo_path'])): ?>
                        <img src="<?= htmlspecialchars($loc['logo_path']) ?>"
                             style="width:28px;height:28px;object-fit:contain;border-radius:4px;border:1px solid #e5e7eb;">
                        <?php else: ?>
                        <div style="width:28px;height:28px;background:#e5e7eb;border-radius:4px;
                                    display:flex;align-items:center;justify-content:center;font-size:0.9rem;">📍</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($loc['name']) ?></div>
                            <div style="font-size:0.75rem;color:#6b7280;"><?= htmlspecialchars($loc['org_name']) ?></div>
                        </div>
                    </div>
                    <input type="color" name="theme_color"
                           value="<?= htmlspecialchars($loc['theme_color'] ?? $themePrimary) ?>"
                           style="width:44px;height:34px;padding:2px;border:1.5px solid #d1d5db;
                                  border-radius:6px;cursor:pointer;">
                    <?php if (!empty($loc['theme_color'])): ?>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;
                                 background:<?= $loc['theme_color'] ?>;"></span>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-secondary">Opslaan</button>
                    <?php if (!empty($loc['theme_color'])): ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="form" value="location_color">
                        <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                        <input type="hidden" name="theme_color" value="">
                        <button type="submit" class="btn btn-sm btn-secondary" title="Reset kleur">✕</button>
                    </form>
                    <?php endif; ?>
                </form>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Live preview -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                    👁️ Live preview
                </h3>
                <div id="previewBlock" style="border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
                    <!-- Mini header preview -->
                    <div id="previewHeader"
                         style="background:#1a2332;padding:10px 16px;display:flex;align-items:center;gap:10px;">
                        <div id="previewLogo"
                             style="font-size:1.1rem;font-weight:700;color:white;letter-spacing:-0.01em;">
                            📦 AssetTrack
                        </div>
                        <div id="previewBadge"
                             style="background:#2563eb;color:white;padding:3px 10px;border-radius:20px;
                                    font-size:0.75rem;font-weight:600;">
                            📍 Hoofdlocatie
                        </div>
                        <div style="margin-left:auto;display:flex;gap:6px;">
                            <?php foreach (['🏠','💻','📊','⚙️'] as $ico): ?>
                            <span id="prevNav_<?= $ico ?>"
                                  style="color:rgba(255,255,255,0.6);font-size:0.75rem;padding:4px 8px;border-radius:5px;">
                                <?= $ico ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Accent balk -->
                    <div id="previewAccentBar" style="height:3px;background:#2563eb;"></div>
                    <!-- Content preview -->
                    <div style="padding:14px;background:#f0f4f8;">
                        <div style="display:flex;gap:8px;margin-bottom:10px;">
                            <button id="previewBtn"
                                    style="background:#2563eb;color:white;border:none;padding:6px 14px;
                                           border-radius:7px;font-size:0.8rem;font-weight:600;cursor:default;">
                                + Opslaan
                            </button>
                            <button style="background:white;color:#374151;border:1.5px solid #d1d5db;
                                           padding:6px 14px;border-radius:7px;font-size:0.8rem;font-weight:600;">
                                Annuleren
                            </button>
                        </div>
                        <div style="background:white;border-radius:8px;border:1px solid #e2e8f0;padding:12px;">
                            <div style="font-size:0.78rem;color:#64748b;">Voorbeeld asset</div>
                            <div id="previewFont" style="font-size:1rem;font-weight:700;margin-top:3px;">
                                AT-001 — Dell Latitude 5520
                            </div>
                        </div>
                    </div>
                </div>
                <p style="font-size:0.75rem;color:#6b7280;margin-top:8px;">
                    Preview wordt bijgewerkt terwijl je kleuren aanpast.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Sync kleurpicker <-> tekstinput
document.querySelectorAll('input[type="color"]').forEach(function(picker) {
    var name = picker.name;
    var textEl = document.getElementById(name + '_text');
    if (!textEl) return;
    picker.addEventListener('input', function() {
        textEl.value = this.value;
        updatePreview();
    });
    textEl.addEventListener('input', function() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
            picker.value = this.value;
            updatePreview();
        }
    });
});

function syncColor(name, val) {
    var picker = document.querySelector('input[type="color"][name="' + name + '"]');
    if (picker && /^#[0-9a-fA-F]{6}$/.test(val)) { picker.value = val; }
    updatePreview();
}

function applyPalette(p, s, a) {
    // Stel alle pickers in
    ['theme_primary', 'theme_secondary', 'theme_accent'].forEach(function(n, i) {
        var val = [p, s, a][i];
        var picker = document.querySelector('input[type="color"][name="' + n + '"]');
        var text   = document.getElementById(n + '_text');
        if (picker) picker.value = val;
        if (text)   text.value  = val;
    });
    updatePreview();
}

function updatePreview() {
    var p = document.querySelector('input[type="color"][name="theme_primary"]').value;
    var s = document.querySelector('input[type="color"][name="theme_secondary"]').value;
    document.getElementById('previewHeader').style.background  = s;
    document.getElementById('previewBadge').style.background   = p;
    document.getElementById('previewAccentBar').style.background = p;
    document.getElementById('previewBtn').style.background     = p;
}

function previewFont(font) {
    var slug = font.replace(/ /g, '+');
    var link = document.createElement('link');
    link.rel  = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=' + slug + ':wght@400;600&display=swap';
    document.head.appendChild(link);
    setTimeout(function() {
        document.getElementById('previewFont').style.fontFamily = "'" + font + "', sans-serif";
        document.getElementById('fontPreview').style.fontFamily = "'" + font + "', sans-serif";
    }, 400);
}

// Init preview
updatePreview();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
