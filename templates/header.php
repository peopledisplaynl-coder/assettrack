<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

// ── Thema instellingen ────────────────────────────────────────────
$company        = queryOne("SELECT * FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
$appName        = !empty($company['app_name'])        ? $company['app_name']        : 'AssetTrack';
$appLogo        = !empty($company['app_logo'])        ? $company['app_logo']        : null;
$themePrimary   = !empty($company['theme_primary'])   ? $company['theme_primary']   : '#2563eb';
$themeSecondary = !empty($company['theme_secondary']) ? $company['theme_secondary'] : '#1a2332';
$themeAccent    = !empty($company['theme_accent'])    ? $company['theme_accent']    : '#3b82f6';
$themeFont      = !empty($company['font_family'])     ? $company['font_family']     : 'DM Sans';

// ── Locatie info ──────────────────────────────────────────────────
$userLocations     = getUserLocations();
$currentLocationId = getLocationId();
$currentLocation   = null;
$locationColor     = null;
foreach ($userLocations as $loc) {
    if ($loc['id'] === $currentLocationId) { $currentLocation = $loc; break; }
}
if ($currentLocationId) {
    $locData = queryOne("SELECT logo_path, theme_color FROM locations WHERE id = ?", [$currentLocationId]);
    $locationColor = !empty($locData['theme_color']) ? $locData['theme_color'] : null;
    if ($currentLocation && !empty($locData['logo_path'])) {
        $currentLocation['logo_path'] = $locData['logo_path'];
    }
}
$activePrimary = $locationColor ?? $themePrimary;

function darkenHex(string $hex, int $amt = 30): string {
    $hex = ltrim($hex,'#');
    return sprintf('#%02x%02x%02x',
        max(0, hexdec(substr($hex,0,2))-$amt),
        max(0, hexdec(substr($hex,2,2))-$amt),
        max(0, hexdec(substr($hex,4,2))-$amt));
}
function hexRgb(string $hex): string {
    $hex = ltrim($hex,'#');
    return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
}

$multipleLocations = count($userLocations) > 1;
$isSuperadminAll   = getRole() === 'superadmin' && !$currentLocation;
$badgeLabel        = $currentLocation ? $currentLocation['name'] : ($isSuperadminAll ? 'Alle locaties' : 'Locatie kiezen');
$fontSlug = urlencode(preg_replace('/[^a-zA-Z0-9 ]/', '', $themeFont));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? $appName) ?> — <?= htmlspecialchars($appName) ?></title>
<meta name="theme-color" content="<?= htmlspecialchars($themeSecondary) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($appName) ?>">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?= $fontSlug ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --color-primary:      <?= $activePrimary ?>;
    --color-primary-dark: <?= darkenHex($activePrimary) ?>;
    --color-primary-rgb:  <?= hexRgb($activePrimary) ?>;
    --color-secondary:    <?= $themeSecondary ?>;
    --color-accent:       <?= $themeAccent ?>;
    --font-base: '<?= htmlspecialchars($themeFont) ?>', system-ui, -apple-system, sans-serif;
    <?php if ($locationColor): ?>--loc-color: <?= $locationColor ?>;<?php endif; ?>
}
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- iOS installatie banner -->
<div id="iosBanner" style="display:none;background:<?= $themeSecondary ?>;color:white;
     padding:12px 20px;text-align:center;font-size:0.85rem;position:relative;z-index:200;
     border-bottom:2px solid <?= $activePrimary ?>;">
    📱 <strong>Installeer <?= htmlspecialchars($appName) ?> als app:</strong>
    tik op <span style="background:rgba(255,255,255,0.2);border-radius:4px;
                 padding:2px 8px;margin:0 3px;font-weight:700;">Deel ⎋</span>
    en kies <span style="background:rgba(255,255,255,0.2);border-radius:4px;
                 padding:2px 8px;margin:0 3px;font-weight:700;">Zet op beginscherm</span>
    <button onclick="dismissIOSBanner()"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                   background:none;border:none;color:rgba(255,255,255,0.7);
                   font-size:1.3rem;cursor:pointer;line-height:1;">✕</button>
</div>

<!-- Android installatie banner -->
<div id="androidBanner" style="display:none;background:<?= $themeSecondary ?>;color:white;
     padding:12px 20px;display:none;align-items:center;justify-content:center;
     gap:12px;font-size:0.85rem;position:relative;z-index:200;
     border-bottom:2px solid <?= $activePrimary ?>;">
    <span>📱 <strong><?= htmlspecialchars($appName) ?></strong> installeren als app?</span>
    <button id="androidInstallBtn" onclick="doAndroidInstall()"
            style="background:<?= $activePrimary ?>;color:white;border:none;
                   padding:6px 16px;border-radius:6px;font-weight:700;cursor:pointer;font-size:0.85rem;">
        Installeren
    </button>
    <button onclick="dismissAndroidBanner()"
            style="background:none;border:none;color:rgba(255,255,255,0.6);
                   font-size:1.2rem;cursor:pointer;padding:0 4px;">✕</button>
</div>

<header class="main-header">
<div class="header-inner">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>/dashboard.php" class="header-logo">
        <?php if ($appLogo): ?>
        <img src="<?= htmlspecialchars($appLogo) ?>" alt="<?= htmlspecialchars($appName) ?>">
        <?php else: ?>
        <span style="font-size:1.4rem;line-height:1;">📦</span>
        <?php endif; ?>
        <span class="header-logo-text"><?= htmlspecialchars($appName) ?></span>
    </a>

    <!-- Locatie badge -->
    <div class="location-badge-wrap">
    <?php if ($multipleLocations): ?>
        <button type="button" class="location-badge<?= $locationColor ? ' has-color' : '' ?>"
                onclick="toggleLocDropdown(event)">
            <?php if (!empty($currentLocation['logo_path'])): ?>
            <img src="<?= htmlspecialchars($currentLocation['logo_path']) ?>"
                 style="width:18px;height:18px;object-fit:contain;border-radius:3px;">
            <?php else: ?>
            <span class="loc-dot"></span>
            <?php endif; ?>
            <?= htmlspecialchars($badgeLabel) ?>
            <span style="opacity:0.6;font-size:0.65rem;margin-left:2px;">▾</span>
        </button>
        <div id="locDropdown" class="location-dropdown">
            <?php foreach ($userLocations as $uloc):
                $ud    = queryOne("SELECT theme_color, logo_path FROM locations WHERE id = ?", [$uloc['id']]);
                $uc    = $ud['theme_color'] ?? null;
                $ul    = $ud['logo_path']   ?? null;
            ?>
            <form method="POST" action="<?= BASE_URL ?>/switch_location.php" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="location_id" value="<?= $uloc['id'] ?>">
                <button type="submit" class="loc-dropdown-item">
                    <?php if ($ul): ?>
                    <img src="<?= htmlspecialchars($ul) ?>"
                         style="width:20px;height:20px;object-fit:contain;border-radius:3px;">
                    <?php else: ?>
                    <span class="loc-color-dot"
                          style="background:<?= $uc ?? $activePrimary ?>;"></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($uloc['name']) ?>
                    <?php if ($uloc['id'] === $currentLocationId): ?>
                    <span style="margin-left:auto;font-size:0.75rem;color:rgba(255,255,255,0.5);">✓</span>
                    <?php endif; ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <span class="location-badge<?= $locationColor ? ' has-color' : '' ?>" style="cursor:default;">
            <?php if (!empty($currentLocation['logo_path'])): ?>
            <img src="<?= htmlspecialchars($currentLocation['logo_path']) ?>"
                 style="width:18px;height:18px;object-fit:contain;border-radius:3px;">
            <?php else: ?>
            <span class="loc-dot"></span>
            <?php endif; ?>
            <?= htmlspecialchars($badgeLabel) ?>
        </span>
    <?php endif; ?>
    </div>

    <!-- Hamburger mobiel -->
    <button class="hamburger" onclick="toggleNav()">☰</button>

    <!-- Navigatie -->
    <nav class="main-nav" id="mainNav">
        <ul>
            <li><a href="<?= BASE_URL ?>/dashboard.php"
                   class="<?= basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':'' ?>">
                <span class="nav-icon">🏠</span>Dashboard</a></li>

            <?php if (hasPermission('view_assets')): ?>
            <li><a href="<?= BASE_URL ?>/modules/assets/"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'/assets/')!==false?'active':'' ?>">
                <span class="nav-icon">💻</span>Assets</a></li>
            <?php endif; ?>

            <?php if (hasPermission('manage_users')): ?>
            <li><a href="<?= BASE_URL ?>/modules/users/"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'/users/')!==false?'active':'' ?>">
                <span class="nav-icon">👥</span>Gebruikers</a></li>
            <?php endif; ?>

            <?php if (hasPermission('view_reports')): ?>
            <li><a href="<?= BASE_URL ?>/modules/reports/"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'/reports/')!==false?'active':'' ?>">
                <span class="nav-icon">📊</span>Rapporten</a></li>
            <?php endif; ?>

            <?php if (hasPermission('print_labels')): ?>
            <li><a href="<?= BASE_URL ?>/modules/labels/"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'/labels/')!==false?'active':'' ?>">
                <span class="nav-icon">🏷️</span>Labels</a></li>
            <?php endif; ?>

            <?php if (hasPermission('scan_assets')): ?>
            <li><a href="<?= BASE_URL ?>/modules/assets/scanner.php"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'scanner.php')!==false?'active':'' ?>">
                <span class="nav-icon">📷</span>Scanner</a></li>
            <?php endif; ?>

            <?php if (hasPermission('view_kb')): ?>
            <li><a href="<?= BASE_URL ?>/modules/kb/"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'/kb/')!==false?'active':'' ?>">
                <span class="nav-icon">📚</span>Kennisbank</a></li>
            <?php endif; ?>

            <?php if (hasPermission('manage_settings')): ?>
            <li><a href="<?= BASE_URL ?>/modules/settings/"
                   class="<?= strpos($_SERVER['REQUEST_URI'],'/settings/')!==false?'active':'' ?>">
                <span class="nav-icon">⚙️</span>Instellingen</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Gebruiker -->
    <div class="header-user">
        <div class="header-username">
            <strong><?= htmlspecialchars(getUserName()) ?></strong>
            <?= htmlspecialchars(getRole()) ?>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-secondary">Uitloggen</a>
    </div>

</div>
</header>

<!-- Locatie kleur accent streep -->
<div style="height:3px;background:<?= $activePrimary ?>;"></div>

<script>
function toggleLocDropdown(e) {
    e.stopPropagation();
    var d = document.getElementById('locDropdown');
    if (d) d.classList.toggle('open');
}
function toggleNav() {
    document.getElementById('mainNav').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var d = document.getElementById('locDropdown');
    if (d && !e.target.closest('.location-badge-wrap')) d.classList.remove('open');
    if (!e.target.closest('#mainNav') && !e.target.closest('.hamburger')) {
        var n = document.getElementById('mainNav');
        if (n) n.classList.remove('open');
    }
});
(function(){
    if (/iphone|ipad|ipod/i.test(navigator.userAgent)
        && !window.navigator.standalone
        && !localStorage.getItem('iosBannerDismissed')) {
        document.getElementById('iosBanner').style.display = 'block';
    }
})();
function dismissIOSBanner() {
    document.getElementById('iosBanner').style.display = 'none';
    localStorage.setItem('iosBannerDismissed','1');
}
// ── Service Worker ───────────────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= BASE_URL ?>/assets/sw.js')
        .then(function(reg){ console.log('SW ok:', reg.scope); })
        .catch(function(err){ console.log('SW fout:', err); });
    });
}

// Geen e.preventDefault() — Chrome toont zelf de native install prompt
window.addEventListener('beforeinstallprompt', function(e) {
    console.log('PWA: install prompt beschikbaar');
    // Chrome handelt de mini-infobar zelf af
});
function doAndroidInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(result) {
        if (result.outcome === 'accepted') {
            dismissAndroidBanner();
        }
        deferredPrompt = null;
    });
}
function dismissAndroidBanner() {
    var banner = document.getElementById('androidBanner');
    if (banner) banner.style.display = 'none';
    localStorage.setItem('androidBannerDismissed', '1');
}

// ── iOS install banner ────────────────────────────────────────────
(function(){
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isStandalone = window.navigator.standalone;
    if (isIOS && !isStandalone && !localStorage.getItem('iosBannerDismissed')) {
        document.getElementById('iosBanner').style.display = 'block';
    }
})();
function dismissIOSBanner() {
    document.getElementById('iosBanner').style.display = 'none';
    localStorage.setItem('iosBannerDismissed', '1');
}
</script>

<main>
