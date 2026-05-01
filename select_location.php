<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$locations = getUserLocations();

if (count($locations) === 0) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#dc2626;">Geen toegang</h2>
        <p>U heeft geen toegang tot locaties. Neem contact op met de beheerder.</p>
        <a href="' . BASE_URL . '/logout.php">Uitloggen</a>
    </div>');
}

if (count($locations) === 1) {
    setLocationId($locations[0]['id']);
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token ongeldig.';
    } else {
        $locationId = (int)$_POST['location_id'];
        foreach ($locations as $loc) {
            if ($loc['id'] === $locationId) {
                setLocationId($locationId);
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        }
        $error = 'Ongeldige locatie.';
    }
}

// Laad thema
$company        = queryOne("SELECT * FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
$appName        = !empty($company['app_name'])        ? $company['app_name']        : 'AssetTrack';
$appLogo        = !empty($company['app_logo'])        ? $company['app_logo']        : null;
$themePrimary   = !empty($company['theme_primary'])   ? $company['theme_primary']   : '#2563eb';
$themeSecondary = !empty($company['theme_secondary']) ? $company['theme_secondary'] : '#1a2332';
$themeFont      = !empty($company['font_family'])     ? $company['font_family']     : 'DM Sans';

// Haal locatie extra info op (logo, kleur, adres, assets teller)
$locationData = [];
foreach ($locations as $loc) {
    $extra = queryOne("SELECT logo_path, theme_color, address, city FROM locations WHERE id = ?", [$loc['id']]);
    $count = queryOne("SELECT COUNT(*) as n FROM assets WHERE location_id = ?", [$loc['id']]);
    $locationData[$loc['id']] = [
        'logo_path'   => $extra['logo_path']  ?? null,
        'theme_color' => $extra['theme_color'] ?? null,
        'address'     => $extra['address']    ?? null,
        'city'        => $extra['city']        ?? null,
        'asset_count' => $count['n']           ?? 0,
    ];
}

function darkenHex(string $hex, int $amt = 30): string {
    $hex = ltrim($hex,'#');
    return sprintf('#%02x%02x%02x',
        max(0,hexdec(substr($hex,0,2))-$amt),
        max(0,hexdec(substr($hex,2,2))-$amt),
        max(0,hexdec(substr($hex,4,2))-$amt));
}

$fontSlug = urlencode(preg_replace('/[^a-zA-Z0-9 ]/', '', $themeFont));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($appName) ?> — Kies locatie</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?= $fontSlug ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: '<?= htmlspecialchars($themeFont) ?>', system-ui, sans-serif;
    min-height: 100vh;
    background: #f0f4f8;
    display: flex;
    flex-direction: column;
}

/* Top balk */
.top-bar {
    background: <?= $themeSecondary ?>;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.top-bar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
.top-bar-logo img { height: 28px; object-fit: contain; }
.top-bar-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: white;
    letter-spacing: -0.01em;
}
.top-bar-user {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.6);
    display: flex;
    align-items: center;
    gap: 12px;
}
.top-bar-user strong { color: white; }
.btn-logout {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 0.78rem;
    text-decoration: none;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-logout:hover { background: rgba(255,255,255,0.2); }

/* Accent balk */
.accent-bar { height: 3px; background: <?= $themePrimary ?>; }

/* Hoofdinhoud */
.main-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}
.page-title {
    text-align: center;
    margin-bottom: 40px;
}
.page-title h1 {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.03em;
    margin-bottom: 6px;
}
.page-title p { color: #64748b; font-size: 0.95rem; }

/* Locatie grid */
.location-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    width: 100%;
    max-width: 960px;
}

/* Locatie kaart */
.location-card {
    background: white;
    border-radius: 16px;
    border: 2px solid #e2e8f0;
    overflow: hidden;
    transition: all 0.2s;
    cursor: pointer;
}
.location-card:hover {
    border-color: var(--loc-primary, <?= $themePrimary ?>);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    transform: translateY(-3px);
}
.location-card-header {
    background: var(--loc-header, <?= $themeSecondary ?>);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 80px;
}
.location-card-logo {
    width: 48px; height: 48px;
    object-fit: contain;
    border-radius: 8px;
    background: rgba(255,255,255,0.15);
    padding: 4px;
    flex-shrink: 0;
}
.location-card-icon {
    width: 48px; height: 48px;
    border-radius: 8px;
    background: rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.location-card-title {
    flex: 1;
}
.location-card-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: white;
    line-height: 1.2;
}
.location-card-org {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.65);
    margin-top: 2px;
}
.location-card-body {
    padding: 16px 20px;
}
.location-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.82rem;
    margin-bottom: 14px;
    min-height: 18px;
}
.location-stats {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}
.location-stat {
    flex: 1;
    text-align: center;
    background: #f8fafc;
    border-radius: 8px;
    padding: 8px;
}
.location-stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
}
.location-stat-label {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 2px;
}
.btn-choose {
    width: 100%;
    padding: 11px;
    background: var(--loc-primary, <?= $themePrimary ?>);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.btn-choose:hover {
    background: var(--loc-primary-dark, <?= darkenHex($themePrimary) ?>);
    transform: translateY(-1px);
}

footer {
    text-align: center;
    padding: 20px;
    color: #94a3b8;
    font-size: 0.78rem;
}

@media (max-width: 600px) {
    .location-grid { grid-template-columns: 1fr; }
    .page-title h1 { font-size: 1.4rem; }
}
</style>
</head>
<body>

<!-- Top balk -->
<div class="top-bar">
    <div class="top-bar-logo">
        <?php if ($appLogo): ?>
        <img src="<?= htmlspecialchars($appLogo) ?>" alt="<?= htmlspecialchars($appName) ?>">
        <?php else: ?>
        <span style="font-size:1.3rem;">📦</span>
        <?php endif; ?>
        <span class="top-bar-name"><?= htmlspecialchars($appName) ?></span>
    </div>
    <div class="top-bar-user">
        <span>Ingelogd als <strong><?= htmlspecialchars(getUserName()) ?></strong></span>
        <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">Uitloggen</a>
    </div>
</div>
<div class="accent-bar"></div>

<!-- Hoofdinhoud -->
<div class="main-wrap">
    <div class="page-title">
        <h1>Kies een locatie</h1>
        <p>Selecteer de locatie waarvoor je <?= htmlspecialchars($appName) ?> wil gebruiken.</p>
    </div>

    <?php
    // Haal unieke organisaties op via org_name uit de locaties
    $orgNames = [];
    foreach ($locations as $loc) {
        $on = $loc['org_name'] ?? '';
        if ($on && !in_array($on, $orgNames)) $orgNames[] = $on;
    }
    $filterOrgName = trim($_GET['org'] ?? '');
    $multiOrg = count($orgNames) > 1;
    ?>

    <?php if ($multiOrg): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:28px;">
        <a href="<?= BASE_URL ?>/select_location.php"
           style="display:inline-flex;align-items:center;padding:8px 20px;border-radius:999px;
                  font-size:0.875rem;font-weight:700;text-decoration:none;transition:all 0.15s;
                  background:<?= !$filterOrgName ? $themePrimary : 'white' ?>;
                  color:<?= !$filterOrgName ? 'white' : '#374151' ?>;
                  border:2px solid <?= !$filterOrgName ? $themePrimary : '#d1d5db' ?>;
                  box-shadow:<?= !$filterOrgName ? '0 2px 8px rgba(0,0,0,0.15)' : 'none' ?>;">
            Alle organisaties
        </a>
        <?php foreach ($orgNames as $on): ?>
        <a href="?org=<?= urlencode($on) ?>"
           style="display:inline-flex;align-items:center;padding:8px 20px;border-radius:999px;
                  font-size:0.875rem;font-weight:700;text-decoration:none;transition:all 0.15s;
                  background:<?= $filterOrgName===$on ? $themePrimary : 'white' ?>;
                  color:<?= $filterOrgName===$on ? 'white' : '#374151' ?>;
                  border:2px solid <?= $filterOrgName===$on ? $themePrimary : '#d1d5db' ?>;
                  box-shadow:<?= $filterOrgName===$on ? '0 2px 8px rgba(0,0,0,0.15)' : 'none' ?>;">
            <?= htmlspecialchars($on) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;
                padding:12px 20px;margin-bottom:20px;font-size:0.875rem;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="location-grid">
        <?php
        $visibleLocations = $filterOrgName
            ? array_filter($locations, fn($l) => ($l['org_name'] ?? '') === $filterOrgName)
            : $locations;
        ?>
        <?php if (empty($visibleLocations)): ?>
        <div style="text-align:center;padding:40px;color:#64748b;grid-column:1/-1;">
            Geen locaties gevonden voor deze organisatie.
        </div>
        <?php endif; ?>
        <?php foreach ($visibleLocations as $loc):
            $ld        = $locationData[$loc['id']] ?? [];
            $locColor  = $ld['theme_color'] ?? $themePrimary;
            $locHeader = $ld['theme_color'] ? darkenHex($ld['theme_color'], 20) : $themeSecondary;
            $locLogo   = $ld['logo_path']   ?? null;
            $locAddr   = trim(($ld['address'] ?? '') . ($ld['city'] ? ', ' . $ld['city'] : ''));
            $assetCount= $ld['asset_count'] ?? 0;

            // Status verdeling voor deze locatie
            $statusCounts = query("SELECT status, COUNT(*) as n FROM assets WHERE location_id = ? GROUP BY status", [$loc['id']]);
            $inUse = 0; $available = 0;
            foreach ($statusCounts as $sc) {
                if ($sc['status'] === 'In gebruik')   $inUse     = $sc['n'];
                if ($sc['status'] === 'Beschikbaar')  $available = $sc['n'];
            }
        ?>
        <div class="location-card"
             style="--loc-primary:<?= $locColor ?>;--loc-primary-dark:<?= darkenHex($locColor) ?>;--loc-header:<?= $locHeader ?>;">
            <div class="location-card-header">
                <?php if ($locLogo): ?>
                <img src="<?= htmlspecialchars($locLogo) ?>"
                     class="location-card-logo"
                     alt="<?= htmlspecialchars($loc['name']) ?>">
                <?php else: ?>
                <div class="location-card-icon">📍</div>
                <?php endif; ?>
                <div class="location-card-title">
                    <div class="location-card-name"><?= htmlspecialchars($loc['name']) ?></div>
                    <div class="location-card-org"><?= htmlspecialchars($loc['org_name'] ?? '') ?></div>
                </div>
            </div>
            <div class="location-card-body">
                <div class="location-meta">
                    <?php if ($locAddr): ?>
                    <span>📍 <?= htmlspecialchars($locAddr) ?></span>
                    <?php endif; ?>
                </div>
                <div class="location-stats">
                    <div class="location-stat">
                        <div class="location-stat-value"><?= $assetCount ?></div>
                        <div class="location-stat-label">Totaal assets</div>
                    </div>
                    <div class="location-stat">
                        <div class="location-stat-value" style="color:#10b981;"><?= $inUse ?></div>
                        <div class="location-stat-label">In gebruik</div>
                    </div>
                    <div class="location-stat">
                        <div class="location-stat-value" style="color:#3b82f6;"><?= $available ?></div>
                        <div class="location-stat-label">Beschikbaar</div>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                    <button type="submit" class="btn-choose">
                        Deze locatie kiezen →
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<footer>
    © <?= date('Y') ?> <?= htmlspecialchars($appName) ?>
</footer>

</body>
</html>
