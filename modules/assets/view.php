<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('view_assets');

$id    = (int)($_GET['id'] ?? 0);
$asset = getAssetById($id);

if (!$asset) {
    header('Location: ' . BASE_URL . '/modules/assets/?error=Asset+niet+gevonden');
    exit;
}

$asset     = calculateAssetFields($asset);
$pageTitle = 'Asset ' . $asset['asset_number'];
include __DIR__ . '/../../templates/header.php';

$warrantyExpired  = !empty($asset['warranty_end_date']) && isDateInPast($asset['warranty_end_date']);
$replacementSoon  = !empty($asset['replacement_due_date']) && isDateWithinMonths($asset['replacement_due_date'], 6);
?>

<div class="page-header">
    <h1><?= htmlspecialchars($asset['asset_number']) ?> — <?= htmlspecialchars($asset['brand'] . ' ' . $asset['model']) ?></h1>
    <div style="display:flex;gap:10px;">
        <?php if (hasPermission('edit_assets') && canEditLocation((int)$asset['location_id'])): ?>
        <a href="<?= BASE_URL ?>/modules/assets/edit.php?id=<?= $asset['id'] ?>" class="btn btn-primary">Bewerken</a>
        <?php endif; ?>
        <?php if (hasPermission('print_labels')): ?>
        <a href="<?= BASE_URL ?>/modules/labels/?asset_id=<?= $asset['id'] ?>" class="btn btn-secondary">🏷️ Label</a>
        <?php endif; ?>
        <?php if (hasPermission('delete_assets')): ?>
        <a href="<?= BASE_URL ?>/modules/assets/delete.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">Verwijderen</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug</a>
    </div>
</div>

<?php if ($warrantyExpired): ?>
<div class="alert alert-danger">⚠️ Garantie is verlopen op <?= formatDate($asset['warranty_end_date']) ?></div>
<?php endif; ?>
<?php if ($replacementSoon): ?>
<div class="alert alert-warning">🔁 Vervanging gepland binnen 6 maanden: <?= formatDate($asset['replacement_due_date']) ?></div>
<?php endif; ?>

<!-- Status badge -->
<div style="margin-bottom:20px;">
    <?php $colors = ['In gebruik'=>'badge-success','Beschikbaar'=>'badge-info','In reparatie'=>'badge-warning','Buiten gebruik'=>'badge-danger','Afgevoerd'=>'badge-secondary']; ?>
    <span class="badge <?= $colors[$asset['status']] ?? 'badge-secondary' ?>" style="font-size:1rem;padding:6px 16px;">
        <?= htmlspecialchars($asset['status']) ?>
    </span>
</div>

<!-- Fotogalerij -->
<?php $assetImages = getAssetImages($asset['id']); ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Fotogalerij</h3>
            <?php if (hasPermission('edit_assets') && canEditLocation((int)$asset['location_id'])): ?>
            <a href="<?= BASE_URL ?>/modules/assets/images.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">Foto's beheren</a>
            <?php endif; ?>
        </div>

        <?php if (empty($assetImages)): ?>
            <p>Geen foto's beschikbaar.</p>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
                <?php foreach ($assetImages as $index => $image): ?>
                <div class="asset-thumb" style="border-radius:12px;overflow:hidden;cursor:pointer;position:relative;" data-image="<?= BASE_URL ?>/assets/uploads/asset_images/<?= htmlspecialchars($image['filename']) ?>">
                    <img src="<?= BASE_URL ?>/assets/uploads/asset_images/<?= htmlspecialchars($image['filename']) ?>" alt="<?= htmlspecialchars($image['original_name']) ?>" style="width:100%;height:140px;object-fit:cover;display:block;">
                    <?php if ($index === 0): ?>
                    <span style="position:absolute;top:10px;left:10px;background:#2563eb;color:#fff;padding:4px 10px;border-radius:999px;font-size:0.75rem;font-weight:600;">Hoofdfoto</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.asset-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 50;
    background: rgba(15, 23, 42, 0.92);
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.asset-lightbox.open {
    display: flex;
}
.asset-lightbox img {
    max-width: calc(100% - 40px);
    max-height: calc(100% - 40px);
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,.45);
}
.asset-lightbox .close-lightbox {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.12);
    border: none;
    color: #fff;
    padding: 10px 14px;
    border-radius: 999px;
    font-size: 1.2rem;
    cursor: pointer;
}
</style>

<div id="assetLightbox" class="asset-lightbox" onclick="closeAssetLightbox(event)">
    <button type="button" class="close-lightbox">✕</button>
    <img id="assetLightboxImage" src="" alt="Grote afbeelding">
</div>

<script>
    document.querySelectorAll('.asset-thumb').forEach(item => {
        item.addEventListener('click', function() {
            const src = this.getAttribute('data-image');
            const lightbox = document.getElementById('assetLightbox');
            const img = document.getElementById('assetLightboxImage');
            img.src = src;
            lightbox.classList.add('open');
        });
    });

    function closeAssetLightbox(event) {
        if (event.target.id === 'assetLightbox' || event.target.classList.contains('close-lightbox')) {
            document.getElementById('assetLightbox').classList.remove('open');
            document.getElementById('assetLightboxImage').src = '';
        }
    }
</script>

<!-- SECTIE 1: Algemeen -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Algemeen</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
            <?php
            $fields = [
                'Assetnummer'    => $asset['asset_number'],
                'Merk'           => $asset['brand'] ?? '',
                'Model'          => $asset['model'] ?? '',
                'Soort'          => $asset['type'] ?? '',
                'Serienummer'    => $asset['serial_number'] ?? '',
                'Ruimte'         => $asset['room'] ?? '',
                'Locatie'        => $asset['location_name'] ?? '',
                'Bedrijfskritisch' => ($asset['business_critical'] ?? 0) ? '✅ Ja' : 'Nee',
            ];
            foreach ($fields as $label => $value):
                if (empty($value)) continue;
            ?>
            <div>
                <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;font-weight:600;"><?= $label ?></div>
                <div style="margin-top:3px;"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($asset['manufacturer_url'])): ?>
            <div>
                <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;font-weight:600;">Fabrikant</div>
                <div style="margin-top:3px;"><a href="<?= htmlspecialchars($asset['manufacturer_url']) ?>" target="_blank" rel="noopener">🔗 Product/Handleiding</a></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SECTIE 2: Gebruik -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Gebruik</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
            <?php
            $fields = [
                'In gebruik bij'      => $asset['assigned_to'] ?? '',
                'Meest recente gebruiker' => $asset['most_recent_user'] ?? '',
                'Geïnstalleerd op'    => formatDate($asset['installed_date'] ?? ''),
            ];
            foreach ($fields as $label => $value):
                if (empty($value)) continue;
            ?>
            <div>
                <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;font-weight:600;"><?= $label ?></div>
                <div style="margin-top:3px;"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTIE 3: Financieel -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Financieel & Garantie</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
            <?php
            $fields = [
                'Aankoopdatum'         => formatDate($asset['purchase_date'] ?? ''),
                'Leeftijd'             => $asset['age_years'] ? number_format($asset['age_years'], 1) . ' jaar' : '',
                'Afschrijvingstermijn' => ($asset['depreciation_years'] ?? '') ? $asset['depreciation_years'] . ' jaar' : '',
                'Vervangingsdatum'     => formatDate($asset['replacement_due_date'] ?? ''),
                'Advies vervanging'    => formatDate($asset['advised_replacement_date'] ?? ''),
                'Einde garantie'       => formatDate($asset['warranty_end_date'] ?? ''),
                'Autoupdate vervalt'   => formatDate($asset['autoupdate_expiry'] ?? ''),
            ];
            foreach ($fields as $label => $value):
                if (empty($value)) continue;
            ?>
            <div>
                <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;font-weight:600;"><?= $label ?></div>
                <div style="margin-top:3px;"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTIE 4: Netwerk -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Netwerk</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
            <?php
            $fields = [
                'MAC-adres'            => $asset['mac_address'] ?? '',
                'LAN IP-adres'         => $asset['lan_ip_address'] ?? '',
                'Management IP'        => $asset['management_ip'] ?? '',
                'Access Point nummer'  => $asset['access_point_number'] ?? '',
            ];
            foreach ($fields as $label => $value):
                if (empty($value)) continue;
            ?>
            <div>
                <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;font-weight:600;"><?= $label ?></div>
                <div style="margin-top:3px;font-family:monospace;"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTIE 5: Hardware -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Hardware</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
            <?php
            $fields = [
                'RAM'              => $asset['ram'] ?? '',
                'CPU'              => $asset['cpu'] ?? '',
                'Besturingssysteem' => $asset['operating_system'] ?? '',
                'Monitor type'     => $asset['touchscreen_monitor_type'] ?? '',
                'Aantal monitoren' => $asset['monitor_count'] ?? '',
                'Serienummer monitor' => $asset['monitor_serial'] ?? '',
                'Telefoon nummer'  => $asset['phone_number'] ?? '',
            ];
            foreach ($fields as $label => $value):
                if (empty($value)) continue;
            ?>
            <div>
                <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;font-weight:600;"><?= $label ?></div>
                <div style="margin-top:3px;"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($asset['notes'])): ?>
<!-- Opmerking -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Opmerking</h3>
        <p style="white-space:pre-wrap;"><?= htmlspecialchars($asset['notes']) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Kennisbank artikelen voor dit asset -->
<?php
$kbArticles = query(
    "SELECT DISTINCT a.id, a.title, c.name as category_name, c.icon as category_icon
     FROM kb_articles a
     LEFT JOIN kb_categories c ON a.category_id = c.id
     LEFT JOIN kb_article_assets ka ON ka.article_id = a.id
     WHERE a.active = 1 AND (
         ka.asset_id = ?
         OR (a.asset_type IS NOT NULL AND a.asset_type = ?)
         OR (a.brand IS NOT NULL AND a.brand = ?)
     )
     ORDER BY ka.asset_id DESC, a.updated_at DESC LIMIT 6",
    [$asset['id'], $asset['type'] ?? '', $asset['brand'] ?? '']
);
?>
<?php if (!empty($kbArticles) && hasPermission('view_kb')): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="margin:0;color:#1a2332;">📚 Kennisbank</h3>
            <a href="<?= BASE_URL ?>/modules/kb/" class="btn btn-secondary btn-sm">Alle artikelen</a>
        </div>
        <div style="display:grid;gap:8px;">
            <?php foreach ($kbArticles as $art): ?>
            <a href="<?= BASE_URL ?>/modules/kb/article.php?id=<?= $art['id'] ?>"
               style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                      background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;
                      text-decoration:none;color:#1a2332;">
                <span style="font-size:1.2rem;"><?= $art['category_icon'] ?? '📄' ?></span>
                <div style="flex:1;">
                    <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($art['title']) ?></div>
                    <div style="font-size:0.75rem;color:#6b7280;"><?= htmlspecialchars($art['category_name'] ?? '') ?></div>
                </div>
                <span style="color:#94a3b8;">→</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Gekoppelde assets -->
<?php
$relationType = [
    'peripheral'  => ['label' => 'Randapparaat',     'icon' => '🔗'],
    'child'       => ['label' => 'Onderdeel van',    'icon' => '👶'],
    'replacement' => ['label' => 'Vervanging van',   'icon' => '🔄'],
    'network'     => ['label' => 'Netwerkkoppeling', 'icon' => '🌐'],
];

$childRelations = query(
    "SELECT ar.*, a.asset_number, a.brand, a.model, a.type, a.status, a.room,
            ar.relation_type, ar.notes as rel_notes, ar.id as rel_id
     FROM asset_relations ar
     JOIN assets a ON ar.related_id = a.id
     WHERE ar.asset_id = ?
     ORDER BY ar.relation_type, a.asset_number",
    [$asset['id']]
);

$parentRelations = query(
    "SELECT ar.*, a.asset_number, a.brand, a.model, a.type, a.status, a.room,
            ar.relation_type, ar.notes as rel_notes, ar.id as rel_id
     FROM asset_relations ar
     JOIN assets a ON ar.asset_id = a.id
     WHERE ar.related_id = ?
     ORDER BY ar.relation_type, a.asset_number",
    [$asset['id']]
);
?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <h3 style="margin:0;color:#1a2332;">🔗 Gekoppelde assets</h3>
            <?php if (hasPermission('edit_assets')): ?>
            <button onclick="toggleRelationForm()" class="btn btn-secondary btn-sm">
                + Koppeling toevoegen
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($childRelations) && empty($parentRelations)): ?>
        <p style="color:#6b7280;font-size:0.875rem;">Geen gekoppelde assets.</p>
        <?php endif; ?>

        <?php if (!empty($childRelations)): ?>
        <div style="margin-bottom:15px;">
            <div style="font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:8px;">
                Gekoppeld aan dit asset
            </div>
            <div style="display:grid;gap:8px;">
                <?php foreach ($childRelations as $rel):
                $rt = $relationType[$rel['relation_type']] ?? ['label'=>$rel['relation_type'],'icon'=>'🔗'];
                $statusColors = ['In gebruik'=>'#10b981','Beschikbaar'=>'#3b82f6',
                                 'In reparatie'=>'#f59e0b','Buiten gebruik'=>'#ef4444','Afgevoerd'=>'#6b7280'];
                $sc = $statusColors[$rel['status']] ?? '#6b7280';
                ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                            background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;">
                    <span style="font-size:1.2rem;"><?= $rt['icon'] ?></span>
                    <div style="flex:1;">
                        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $rel['related_id'] ?>"
                           style="font-weight:600;color:#2563eb;text-decoration:none;">
                            <?= htmlspecialchars($rel['asset_number']) ?>
                        </a>
                        <span style="color:#6b7280;font-size:0.875rem;">
                            — <?= htmlspecialchars(trim(($rel['brand']??'').' '.($rel['model']??''))) ?>
                            (<?= htmlspecialchars($rel['type']??'') ?>)
                        </span>
                        <?php if ($rel['rel_notes']): ?>
                        <span style="color:#6b7280;font-size:0.8rem;display:block;">
                            <?= htmlspecialchars($rel['rel_notes']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <span style="font-size:0.75rem;background:#e5e7eb;padding:2px 8px;border-radius:4px;">
                        <?= $rt['label'] ?>
                    </span>
                    <span style="font-size:0.75rem;color:white;background:<?= $sc ?>;padding:2px 8px;border-radius:4px;">
                        <?= htmlspecialchars($rel['status']) ?>
                    </span>
                    <?php if (hasPermission('edit_assets')): ?>
                    <form method="POST" action="<?= BASE_URL ?>/modules/assets/relations.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="relation_id" value="<?= $rel['rel_id'] ?>">
                        <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                        <button type="submit" onclick="return confirm('Koppeling verwijderen?')"
                                style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1.1rem;">✕</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($parentRelations)): ?>
        <div style="margin-bottom:15px;">
            <div style="font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:8px;">
                Dit asset is gekoppeld aan
            </div>
            <div style="display:grid;gap:8px;">
                <?php foreach ($parentRelations as $rel):
                $rt = $relationType[$rel['relation_type']] ?? ['label'=>$rel['relation_type'],'icon'=>'🔗'];
                ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                            background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;">
                    <span style="font-size:1.2rem;"><?= $rt['icon'] ?></span>
                    <div style="flex:1;">
                        <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $rel['asset_id'] ?>"
                           style="font-weight:600;color:#2563eb;text-decoration:none;">
                            <?= htmlspecialchars($rel['asset_number']) ?>
                        </a>
                        <span style="color:#6b7280;font-size:0.875rem;">
                            — <?= htmlspecialchars(trim(($rel['brand']??'').' '.($rel['model']??''))) ?>
                            (<?= htmlspecialchars($rel['type']??'') ?>)
                        </span>
                    </div>
                    <span style="font-size:0.75rem;background:#bfdbfe;color:#1e40af;padding:2px 8px;border-radius:4px;">
                        ↑ <?= $rt['label'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Koppeling toevoegen formulier -->
        <?php if (hasPermission('edit_assets')): ?>
        <div id="relationForm" style="display:none;margin-top:15px;padding:15px;
             background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;">
            <h4 style="margin:0 0 12px;">Koppeling toevoegen</h4>
            <form method="POST" action="<?= BASE_URL ?>/modules/assets/relations.php"
                  style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="margin:0;position:relative;">
                        <label>Zoek asset om te koppelen</label>
                        <input type="text" id="relationSearch" class="form-control"
                               placeholder="Assetnummer, merk, model..."
                               oninput="searchRelationAssets(this.value)">
                        <div id="relationResults" style="display:none;position:absolute;z-index:100;
                             background:white;border:1px solid #e5e7eb;border-radius:6px;
                             max-height:200px;overflow-y:auto;width:100%;
                             box-shadow:0 4px 10px rgba(0,0,0,0.1);top:100%;left:0;">
                        </div>
                        <input type="hidden" name="related_id" id="relatedId">
                        <div id="selectedAsset" style="margin-top:6px;font-size:0.875rem;color:#059669;"></div>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Type koppeling</label>
                        <select name="relation_type" class="form-control">
                            <option value="peripheral">🔗 Randapparaat</option>
                            <option value="child">👶 Onderdeel van</option>
                            <option value="replacement">🔄 Vervanging van</option>
                            <option value="network">🌐 Netwerkkoppeling</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Opmerking (optioneel)</label>
                    <input type="text" name="notes" class="form-control"
                           placeholder="bijv. Linker monitor, Poort 12">
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary">Koppeling opslaan</button>
                    <button type="button" onclick="toggleRelationForm()" class="btn btn-secondary">Annuleren</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleRelationForm() {
    const form = document.getElementById('relationForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
let searchTimeout;
function searchRelationAssets(val) {
    clearTimeout(searchTimeout);
    if (val.length < 2) { document.getElementById('relationResults').style.display='none'; return; }
    searchTimeout = setTimeout(async () => {
        const resp = await fetch('<?= BASE_URL ?>/modules/assets/relations.php?search='+encodeURIComponent(val)+'&exclude=<?= $asset['id'] ?>');
        const data = await resp.json();
        const box = document.getElementById('relationResults');
        if (!data.length) { box.style.display='none'; return; }
        box.innerHTML = data.map(a =>
            `<div onclick="selectRelationAsset(${a.id},'${a.asset_number}','${a.brand} ${a.model}','${a.type}')"
                  style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:0.875rem;"
                  onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <strong>${a.asset_number}</strong>
                <span style="color:#6b7280;"> — ${a.brand||''} ${a.model||''} (${a.type||''})</span>
            </div>`
        ).join('');
        box.style.display = 'block';
    }, 300);
}
function selectRelationAsset(id, number, name, type) {
    document.getElementById('relatedId').value = id;
    document.getElementById('relationSearch').value = number;
    document.getElementById('selectedAsset').textContent = '✓ '+number+' — '+name;
    document.getElementById('relationResults').style.display = 'none';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#relationForm')) {
        document.getElementById('relationResults').style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
