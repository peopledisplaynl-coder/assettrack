<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_settings');

$pageTitle = 'Instellingen';
include __DIR__ . '/../../templates/header.php';

$menu = [
    ['url' => 'locations.php',     'icon' => '🏢', 'title' => 'Organisatie & Locaties', 'desc' => 'Beheer organisaties, locaties en ruimtes.'],
    ['url' => 'custom_fields.php', 'icon' => '🔧', 'title' => 'Custom velden',           'desc' => 'Voeg extra velden toe aan het asset formulier.'],
    ['url' => 'asset_types.php',   'icon' => '📝', 'title' => 'Asset soorten',           'desc' => 'Beheer soorten (Laptop, Desktop, Chromebook, etc.) en hernoem ze.'],
    ['url' => 'lists.php',         'icon' => '🏷️', 'title' => 'Merken & Ruimtes',        'desc' => 'Beheer merken en ruimtes per locatie.'],
    ['url' => 'appearance.php',    'icon' => '🎨', 'title' => 'Weergave & Thema',         'desc' => 'Kleuren, lettertype, logo en locatie kleuren.'],
];

if (getRole() === 'superadmin') {
    $menu[] = ['url' => 'system.php', 'icon' => '⚙️', 'title' => 'Systeem & Audit log', 'desc' => 'Bekijk systeeminfo en alle wijzigingen in het systeem.'];
}
?>
<div class="page-header"><h1>⚙️ Instellingen</h1></div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;">
    <?php foreach ($menu as $item): ?>
    <a href="<?= BASE_URL ?>/modules/settings/<?= $item['url'] ?>" style="text-decoration:none;">
        <div class="card" style="margin:0;cursor:pointer;transition:box-shadow 0.2s;"
             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'"
             onmouseout="this.style.boxShadow=''">
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                <div style="font-size:2rem;"><?= $item['icon'] ?></div>
                <h3 style="margin:0;color:#1a2332;"><?= $item['title'] ?></h3>
                <p style="margin:0;color:#6b7280;font-size:0.875rem;"><?= $item['desc'] ?></p>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
