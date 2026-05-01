<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_kb');

$id      = (int)($_GET['id'] ?? 0);
$article = $id ? queryOne("SELECT * FROM kb_articles WHERE id = ?", [$id]) : null;
$isEdit  = $article !== null;
$errors  = [];

// Verwerk formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $action = $_POST['form_action'] ?? 'save';

        if ($action === 'delete' && $isEdit) {
            execute("DELETE FROM kb_articles WHERE id = ?", [$id]);
            header('Location: ' . BASE_URL . '/modules/kb/?success=Artikel+verwijderd');
            exit;
        }

        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);

        if (empty($title))   $errors[] = 'Titel is verplicht.';
        if (empty($content)) $errors[] = 'Inhoud is verplicht.';
        if (!$categoryId)    $errors[] = 'Kies een categorie.';

        if (empty($errors)) {
            $data = [
                'category_id'  => $categoryId,
                'title'        => $title,
                'content'      => $content,
                'asset_type'   => trim($_POST['asset_type'] ?? '') ?: null,
                'brand'        => trim($_POST['brand'] ?? '') ?: null,
                'external_url' => trim($_POST['external_url'] ?? '') ?: null,
                'tags'         => trim($_POST['tags'] ?? '') ?: null,
                'active'       => 1,
            ];

            if ($isEdit) {
                $sets   = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                $params = array_values($data);
                $params[] = $id;
                execute("UPDATE kb_articles SET $sets, updated_at = NOW() WHERE id = ?", $params);
                $articleId = $id;
            } else {
                $data['created_by'] = getUserId();
                $cols   = implode(', ', array_keys($data));
                $places = implode(', ', array_fill(0, count($data), '?'));
                execute("INSERT INTO kb_articles ($cols) VALUES ($places)", array_values($data));
                $articleId = (int)lastInsertId();
            }

            // Verwerk asset koppelingen
            execute("DELETE FROM kb_article_assets WHERE article_id = ?", [$articleId]);
            $linkedIds = array_filter(array_map('intval', $_POST['linked_assets'] ?? []));
            foreach ($linkedIds as $aid) {
                execute("INSERT IGNORE INTO kb_article_assets (article_id, asset_id) VALUES (?, ?)",
                    [$articleId, $aid]);
            }

            header('Location: ' . BASE_URL . '/modules/kb/article.php?id=' . $articleId);
            exit;
        }
    }
}

// Haal bestaande koppelingen op
$linkedAssetIds = [];
if ($isEdit) {
    $existing = query("SELECT asset_id FROM kb_article_assets WHERE article_id = ?", [$id]);
    $linkedAssetIds = array_column($existing, 'asset_id');
}

$categories = query("SELECT * FROM kb_categories WHERE active = 1 ORDER BY sort_order, name");
$allTypes   = getAssetTypes();
$allBrands  = getBrands();
$locationId = getLocationId();

$pageTitle = $isEdit ? 'Artikel bewerken' : 'Artikel toevoegen';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1><?= $isEdit ? 'Artikel bewerken' : 'Nieuw artikel' ?></h1>
    <a href="<?= BASE_URL ?>/modules/kb/" class="btn btn-secondary">← Terug</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="form_action" value="save" id="formAction">

    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

        <!-- Links: inhoud -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Artikel inhoud
                    </h3>
                    <div class="form-group">
                        <label>Titel *</label>
                        <input type="text" name="title" class="form-control" required
                               value="<?= htmlspecialchars($article['title'] ?? '') ?>"
                               placeholder="bijv. Chromebook herstarten na update">
                    </div>
                    <div class="form-group">
                        <label>Inhoud *</label>
                        <textarea name="content" class="form-control" rows="12" required
                                  placeholder="Schrijf de inhoud van het artikel hier...&#10;&#10;Je kunt stappen nummeren:&#10;1. Stap één&#10;2. Stap twee&#10;&#10;Of problemen beschrijven en oplossingen geven."><?= htmlspecialchars($article['content'] ?? '') ?></textarea>
                        <small style="color:#6b7280;">Gebruik enters voor nieuwe regels. Nummering en opsommingen zijn mogelijk.</small>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Externe URL (handleiding, fabrikant, etc.)</label>
                        <input type="text" name="external_url" class="form-control" style="width:100%;"
                               value="<?= htmlspecialchars($article['external_url'] ?? '') ?>"
                               placeholder="https://support.dell.com/...">
                    </div>
                </div>
            </div>

            <!-- Asset koppelingen -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        🔗 Koppelen aan specifieke assets
                    </h3>
                    <p style="color:#6b7280;font-size:0.875rem;margin-bottom:12px;">
                        Optioneel: koppel dit artikel aan specifieke assets.
                        Het artikel is dan direct zichtbaar op die asset pagina's.
                    </p>

                    <!-- Tabs: Zoek individueel / Bulk koppeling -->
                    <div style="display:flex;gap:0;margin-bottom:12px;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
                        <button type="button" onclick="switchTab('single')" id="tabSingle"
                                style="flex:1;padding:8px;border:none;background:#2563eb;color:white;cursor:pointer;font-size:0.875rem;font-weight:600;">
                            🔍 Individueel zoeken
                        </button>
                        <button type="button" onclick="switchTab('bulk')" id="tabBulk"
                                style="flex:1;padding:8px;border:none;background:#f1f5f9;color:#374151;cursor:pointer;font-size:0.875rem;">
                            📦 Bulk koppelen
                        </button>
                    </div>

                    <!-- Individueel zoeken -->
                    <div id="panelSingle">
                        <div style="position:relative;margin-bottom:12px;">
                            <input type="text" id="assetSearch" class="form-control"
                                   placeholder="Zoek op assetnummer, merk of model..."
                                   oninput="searchAssets(this.value)" autocomplete="off">
                            <div id="assetResults" style="display:none;position:absolute;z-index:100;
                                 background:white;border:1px solid #e5e7eb;border-radius:6px;
                                 width:100%;max-height:200px;overflow-y:auto;
                                 box-shadow:0 4px 10px rgba(0,0,0,0.1);top:100%;left:0;">
                            </div>
                        </div>
                    </div>

                    <!-- Bulk koppelen -->
                    <div id="panelBulk" style="display:none;">
                        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px;margin-bottom:12px;">
                            <p style="font-size:0.8rem;color:#1e40af;margin:0 0 10px;">
                                Filter assets en koppel ze allemaal tegelijk aan dit artikel.
                            </p>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                                <div>
                                    <label style="font-size:0.75rem;font-weight:600;">Soort</label>
                                    <select id="bulkType" class="form-control">
                                        <option value="">Alle soorten</option>
                                        <?php foreach ($allTypes as $t): ?>
                                        <option value="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:0.75rem;font-weight:600;">Merk</label>
                                    <input type="text" id="bulkBrand" class="form-control" placeholder="bijv. HP, Acer">
                                </div>
                            </div>
                            <button type="button" onclick="loadBulkAssets()" class="btn btn-secondary" style="width:100%;">
                                🔍 Assets ophalen
                            </button>
                        </div>

                        <div id="bulkResults" style="display:none;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <span id="bulkCount" style="font-size:0.8rem;color:#6b7280;"></span>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" onclick="selectAllBulk(true)" class="btn btn-sm btn-secondary">Alles</button>
                                    <button type="button" onclick="selectAllBulk(false)" class="btn btn-sm btn-secondary">Geen</button>
                                    <button type="button" onclick="addSelectedBulk()" class="btn btn-sm btn-primary">+ Koppelen</button>
                                </div>
                            </div>
                            <div id="bulkList" style="max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;">
                            </div>
                        </div>
                    </div>

                    <!-- Gekoppelde assets lijst -->
                    <div id="linkedAssetsList" style="display:grid;gap:6px;">
                        <?php
                        if (!empty($linkedAssetIds)) {
                            $phs = implode(',', array_fill(0, count($linkedAssetIds), '?'));
                            $linked = query("SELECT a.id, a.asset_number, a.brand, a.model, a.type
                                FROM assets a WHERE a.id IN ($phs)", $linkedAssetIds);
                            foreach ($linked as $la):
                        ?>
                        <div class="linked-asset-item" id="linked_<?= $la['id'] ?>"
                             style="display:flex;align-items:center;gap:10px;padding:8px 10px;
                                    background:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
                            <input type="hidden" name="linked_assets[]" value="<?= $la['id'] ?>">
                            <div style="flex:1;font-size:0.875rem;">
                                <strong><?= htmlspecialchars($la['asset_number']) ?></strong>
                                <span style="color:#6b7280;">
                                    — <?= htmlspecialchars(trim(($la['brand']??'').' '.($la['model']??''))) ?>
                                    (<?= htmlspecialchars($la['type']??'') ?>)
                                </span>
                            </div>
                            <button type="button" onclick="removeLinkedAsset(<?= $la['id'] ?>)"
                                    style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1rem;">✕</button>
                        </div>
                        <?php endforeach; } ?>
                    </div>
                    <p id="noLinkedMsg" style="color:#6b7280;font-size:0.875rem;
                       <?= empty($linkedAssetIds) ? '' : 'display:none;' ?>">
                        Nog geen assets gekoppeld.
                    </p>
                </div>
            </div>
        </div>

        <!-- Rechts: meta -->
        <div>
            <div class="card" style="margin-bottom:15px;">
                <div class="card-body">
                    <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                        Instellingen
                    </h3>
                    <div class="form-group">
                        <label>Categorie *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">-- Kies categorie --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= ($article['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['icon'] ?? '') ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($categories)): ?>
                        <small><a href="<?= BASE_URL ?>/modules/kb/categories.php">Maak eerst een categorie aan →</a></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Van toepassing op soort</label>
                        <select name="asset_type" class="form-control">
                            <option value="">Alle soorten</option>
                            <?php foreach ($allTypes as $t): ?>
                            <option value="<?= htmlspecialchars($t['name']) ?>"
                                <?= ($article['asset_type'] ?? '') === $t['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#6b7280;">Dit artikel verschijnt bij alle assets van dit type.</small>
                    </div>
                    <div class="form-group">
                        <label>Van toepassing op merk</label>
                        <input type="text" name="brand" class="form-control"
                               value="<?= htmlspecialchars($article['brand'] ?? '') ?>"
                               placeholder="bijv. Dell, HP, Acer">
                        <small style="color:#6b7280;">Dit artikel verschijnt bij alle assets van dit merk.</small>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Tags (komma gescheiden)</label>
                        <input type="text" name="tags" class="form-control"
                               value="<?= htmlspecialchars($article['tags'] ?? '') ?>"
                               placeholder="bijv. reparatie, wachtwoord, netwerk">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;margin-bottom:10px;">
                <?= $isEdit ? '💾 Opslaan' : '✅ Artikel aanmaken' ?>
            </button>

            <?php if ($isEdit): ?>
            <button type="button" class="btn btn-danger" style="width:100%;"
                    onclick="if(confirm('Dit artikel definitief verwijderen?')){
                        document.getElementById('formAction').value='delete';
                        this.closest('form').submit();
                    }">
                🗑️ Verwijderen
            </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
let searchTimeout;
const linkedIds = new Set([<?= implode(',', $linkedAssetIds) ?>]);

function searchAssets(val) {
    clearTimeout(searchTimeout);
    const box = document.getElementById('assetResults');
    if (val.length < 2) { box.style.display='none'; return; }

    searchTimeout = setTimeout(async () => {
        const resp = await fetch('<?= BASE_URL ?>/modules/kb/kb_search.php?search=' + encodeURIComponent(val));
        const data = await resp.json();
        if (!data.length) { box.style.display='none'; return; }
        box.innerHTML = data.map(a =>
            `<div onclick="addLinkedAsset(${a.id},'${a.asset_number.replace(/'/g,"\\'")}','${((a.brand||'')+' '+(a.model||'')).trim().replace(/'/g,"\\'")}','${(a.type||'').replace(/'/g,"\\'")}' )"
                  style="padding:8px 12px;cursor:pointer;font-size:0.875rem;border-bottom:1px solid #f3f4f6;
                         ${linkedIds.has(a.id)?'opacity:0.4;pointer-events:none;':''}"
                  onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <strong>${a.asset_number}</strong>
                <span style="color:#6b7280;"> — ${a.brand||''} ${a.model||''} (${a.type||''})</span>
                ${linkedIds.has(a.id) ? ' ✓' : ''}
            </div>`
        ).join('');
        box.style.display = 'block';
    }, 300);
}

function addLinkedAsset(id, number, name, type) {
    if (linkedIds.has(id)) return;
    linkedIds.add(id);

    const list = document.getElementById('linkedAssetsList');
    const div = document.createElement('div');
    div.id = 'linked_' + id;
    div.className = 'linked-asset-item';
    div.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 10px;background:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;';
    div.innerHTML = `<input type="hidden" name="linked_assets[]" value="${id}">
        <div style="flex:1;font-size:0.875rem;">
            <strong>${number}</strong>
            <span style="color:#6b7280;"> — ${name} (${type})</span>
        </div>
        <button type="button" onclick="removeLinkedAsset(${id})"
                style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1rem;">✕</button>`;
    list.appendChild(div);

    document.getElementById('noLinkedMsg').style.display = 'none';
    document.getElementById('assetSearch').value = '';
    document.getElementById('assetResults').style.display = 'none';
}

// Tab switching
function switchTab(tab) {
    document.getElementById('panelSingle').style.display = tab === 'single' ? 'block' : 'none';
    document.getElementById('panelBulk').style.display   = tab === 'bulk'   ? 'block' : 'none';
    document.getElementById('tabSingle').style.background = tab === 'single' ? '#2563eb' : '#f1f5f9';
    document.getElementById('tabSingle').style.color      = tab === 'single' ? 'white'   : '#374151';
    document.getElementById('tabBulk').style.background   = tab === 'bulk'   ? '#2563eb' : '#f1f5f9';
    document.getElementById('tabBulk').style.color        = tab === 'bulk'   ? 'white'   : '#374151';
}

// Bulk laden — gebruikt eigen KB search endpoint zonder limiet
function loadBulkAssets() {
    const type  = document.getElementById('bulkType').value;
    const brand = document.getElementById('bulkBrand').value;
    const count = document.getElementById('bulkCount');
    const list  = document.getElementById('bulkList');
    const res   = document.getElementById('bulkResults');

    if (!type && !brand) {
        alert('Kies minimaal een soort of merk om te filteren.');
        return;
    }

    count.textContent = 'Bezig met ophalen...';
    res.style.display = 'block';
    list.innerHTML = '';

    var url = '<?= BASE_URL ?>/modules/kb/kb_search.php?search=';
    if (type)  url += '&type='  + encodeURIComponent(type);
    if (brand) url += '&brand=' + encodeURIComponent(brand);

    fetch(url)
        .then(function(resp) {
            if (!resp.ok) throw new Error('Server error: ' + resp.status);
            return resp.json();
        })
        .then(function(data) {
            if (!data.length) {
                count.textContent = 'Geen assets gevonden.';
                list.innerHTML = '<div style="padding:10px;color:#6b7280;font-size:0.875rem;">Geen resultaten voor deze filter.</div>';
                return;
            }

            count.textContent = data.length + ' asset(s) gevonden';
            var html = '';
            data.forEach(function(a) {
                var alreadyLinked = linkedIds.has(a.id);
                html += '<label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:0.8rem;">';
                html += '<input type="checkbox" class="bulk-cb"';
                html += ' data-id="' + a.id + '"';
                html += ' data-number="' + (a.asset_number || '').replace(/"/g, '') + '"';
                html += ' data-name="' + ((a.brand||'')+' '+(a.model||'')).replace(/"/g, '') + '"';
                html += ' data-type="' + (a.type||'').replace(/"/g, '') + '"';
                if (alreadyLinked) html += ' checked disabled';
                html += '>';
                html += '<span><strong>' + (a.asset_number||'') + '</strong>';
                html += ' &mdash; ' + (a.brand||'') + ' ' + (a.model||'');
                html += ' (' + (a.type||'') + ')</span>';
                if (alreadyLinked) html += '<span style="color:#059669;font-size:0.75rem;margin-left:auto;">✓ Al gekoppeld</span>';
                html += '</label>';
            });
            list.innerHTML = html;
        })
        .catch(function(err) {
            count.textContent = 'Fout bij ophalen: ' + err.message;
            console.error('KB bulk search error:', err);
        });
}

function selectAllBulk(checked) {
    document.querySelectorAll('.bulk-cb:not(:disabled)').forEach(cb => cb.checked = checked);
}

function addSelectedBulk() {
    document.querySelectorAll('.bulk-cb:checked:not(:disabled)').forEach(cb => {
        const id     = parseInt(cb.dataset.id);
        const number = cb.dataset.number;
        const name   = cb.dataset.name;
        const type   = cb.dataset.type;
        if (!linkedIds.has(id)) addLinkedAsset(id, number, name, type);
    });
}

function removeLinkedAsset(id) {
    linkedIds.delete(id);
    const el = document.getElementById('linked_' + id);
    if (el) el.remove();
    if (document.querySelectorAll('.linked-asset-item').length === 0) {
        document.getElementById('noLinkedMsg').style.display = 'block';
    }
}

document.addEventListener('click', e => {
    if (!e.target.closest('#assetSearch') && !e.target.closest('#assetResults')) {
        document.getElementById('assetResults').style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
