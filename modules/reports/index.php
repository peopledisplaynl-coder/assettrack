<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('view_reports');

$pageTitle = 'Rapporten';
include __DIR__ . '/../../templates/header.php';

$reports = [
    [
        'type'  => 'all',
        'icon'  => '📋',
        'title' => 'Volledig overzicht',
        'desc'  => 'Alle assets met alle velden. Filteren op status, soort en ruimte.',
        'color' => '#2563eb',
        'bg'    => '#eff6ff',
    ],
    [
        'type'  => 'per_location',
        'icon'  => '📍',
        'title' => 'Per locatie',
        'desc'  => 'Samenvatting per locatie met totalen en statusverdeling. Klik door naar details.',
        'color' => '#7c3aed',
        'bg'    => '#f5f3ff',
    ],
    [
        'type'  => 'per_room',
        'icon'  => '🚪',
        'title' => 'Per ruimte',
        'desc'  => 'Samenvatting per ruimte. Klik op een ruimte voor de volledige assetlijst.',
        'color' => '#0891b2',
        'bg'    => '#ecfeff',
    ],
    [
        'type'  => 'per_status',
        'icon'  => '🔄',
        'title' => 'Per status',
        'desc'  => 'Hoeveel assets per status? Klik op een status voor de volledige lijst.',
        'color' => '#059669',
        'bg'    => '#f0fdf4',
    ],
    [
        'type'  => 'warranty',
        'icon'  => '⚠️',
        'title' => 'Verlopen garanties',
        'desc'  => 'Assets waarvan de garantiedatum verstreken is. Gesorteerd op oudste garantie.',
        'color' => '#dc2626',
        'bg'    => '#fff5f5',
    ],
    [
        'type'  => 'replacement',
        'icon'  => '🔁',
        'title' => 'Vervanging komende periode',
        'desc'  => 'Assets die binnenkort vervangen moeten worden op basis van afschrijving.',
        'color' => '#d97706',
        'bg'    => '#fffbeb',
    ],
    [
        'type'  => 'depreciation',
        'icon'  => '📉',
        'title' => 'Afschrijvingsoverzicht',
        'desc'  => 'Leeftijd en resterende afschrijving per asset. Gesorteerd op vervangingsdatum.',
        'color' => '#475569',
        'bg'    => '#f8fafc',
    ],
    [
        'type'  => 'critical',
        'icon'  => '🔴',
        'title' => 'Bedrijfskritische assets',
        'desc'  => 'Alle als bedrijfskritisch gemarkeerde assets met status en netwerkinfo.',
        'color' => '#be123c',
        'bg'    => '#fff1f2',
    ],
];
?>

<div class="page-header">
    <h1>📊 Rapporten</h1>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($reports as $r): ?>
    <a href="<?= BASE_URL ?>/modules/reports/generate.php?type=<?= $r['type'] ?>"
       style="text-decoration:none;">
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;
                    padding:22px;height:100%;display:flex;flex-direction:column;gap:12px;
                    transition:all 0.2s;cursor:pointer;"
             onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)';this.style.transform='translateY(-2px)';this.style.borderColor='<?= $r['color'] ?>'"
             onmouseout="this.style.boxShadow='';this.style.transform='';this.style.borderColor='#e2e8f0'">

            <!-- Icoon -->
            <div style="width:52px;height:52px;border-radius:12px;background:<?= $r['bg'] ?>;
                        display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <?= $r['icon'] ?>
            </div>

            <!-- Titel -->
            <div>
                <h3 style="margin:0 0 6px;font-size:1rem;color:#0f172a;font-weight:700;">
                    <?= htmlspecialchars($r['title']) ?>
                </h3>
                <p style="margin:0;font-size:0.83rem;color:#64748b;line-height:1.5;">
                    <?= htmlspecialchars($r['desc']) ?>
                </p>
            </div>

            <!-- Pijl -->
            <div style="margin-top:auto;display:flex;align-items:center;gap:5px;
                        font-size:0.82rem;font-weight:600;color:<?= $r['color'] ?>;">
                Rapport openen
                <span style="font-size:1rem;">→</span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
