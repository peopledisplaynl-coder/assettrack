<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Al ingelogd — stuur door naar locatiekeuze
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/select_location.php');
    exit;
}

// Laad thema
$company        = queryOne("SELECT * FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
$appName        = !empty($company['app_name'])        ? $company['app_name']        : 'AssetTrack';
$appLogo        = !empty($company['app_logo'])        ? $company['app_logo']        : null;
$themePrimary   = !empty($company['theme_primary'])   ? $company['theme_primary']   : '#2563eb';
$themeSecondary = !empty($company['theme_secondary']) ? $company['theme_secondary'] : '#1a2332';
$themeFont      = !empty($company['font_family'])     ? $company['font_family']     : 'DM Sans';

function darkenHex(string $hex, int $amt = 30): string {
    $hex = ltrim($hex,'#');
    return sprintf('#%02x%02x%02x',
        max(0,hexdec(substr($hex,0,2))-$amt),
        max(0,hexdec(substr($hex,2,2))-$amt),
        max(0,hexdec(substr($hex,4,2))-$amt));
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige aanvraag. Probeer opnieuw.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username)) $errors[] = 'Gebruikersnaam of e-mail is verplicht.';
        if (empty($password)) $errors[] = 'Wachtwoord is verplicht.';

        if (empty($errors)) {
            if (login($username, $password)) {
                header('Location: ' . BASE_URL . '/select_location.php');
                exit;
            } else {
                $errors[] = 'Gebruikersnaam of wachtwoord is onjuist.';
            }
        }
    }
}

$fontSlug = urlencode(preg_replace('/[^a-zA-Z0-9 ]/', '', $themeFont));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($appName) ?> — Inloggen</title>
<meta name="theme-color" content="<?= $themeSecondary ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?= $fontSlug ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { height:100%; }
body {
    font-family: '<?= htmlspecialchars($themeFont) ?>', system-ui, sans-serif;
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: #f0f4f8;
}

/* Linker paneel — visueel */
.login-visual {
    background: <?= $themeSecondary ?>;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 40px;
    position: relative;
    overflow: hidden;
}
.login-visual::before {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    top: -100px; right: -100px;
}
.login-visual::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    bottom: -80px; left: -80px;
}
.visual-logo {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 48px;
    z-index: 1;
}
.visual-logo img { height: 48px; object-fit: contain; }
.visual-logo-icon { font-size: 2.5rem; }
.visual-app-name {
    font-size: 1.8rem;
    font-weight: 800;
    color: white;
    letter-spacing: -0.03em;
}
.visual-tagline {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.6);
    text-align: center;
    line-height: 1.6;
    max-width: 320px;
    z-index: 1;
    margin-bottom: 40px;
}
.visual-features {
    z-index: 1;
    display: grid;
    gap: 12px;
    width: 100%;
    max-width: 320px;
}
.visual-feature {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255,255,255,0.75);
    font-size: 0.88rem;
}
.visual-feature-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

/* Rechter paneel — formulier */
.login-form-wrap {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 40px;
    background: white;
}
.login-form-inner {
    width: 100%;
    max-width: 380px;
}
.login-form-inner h1 {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 6px;
    letter-spacing: -0.03em;
}
.login-form-inner p {
    color: #64748b;
    margin-bottom: 32px;
    font-size: 0.9rem;
}

.form-group { margin-bottom: 18px; }
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.83rem;
    color: #374151;
    margin-bottom: 6px;
}
.form-control {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #d1d5db;
    border-radius: 10px;
    font-size: 0.95rem;
    font-family: inherit;
    color: #0f172a;
    background: white;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-control:focus {
    outline: none;
    border-color: <?= $themePrimary ?>;
    box-shadow: 0 0 0 3px <?= $themePrimary ?>30;
}
.form-control::placeholder { color: #94a3b8; }
.btn-login {
    width: 100%;
    padding: 12px;
    background: <?= $themePrimary ?>;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.15s;
    margin-top: 8px;
}
.btn-login:hover {
    background: <?= darkenHex($themePrimary) ?>;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px <?= $themePrimary ?>50;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 0.875rem;
}
.login-footer {
    text-align: center;
    margin-top: 32px;
    color: #94a3b8;
    font-size: 0.78rem;
}
.login-footer a { color: #64748b; text-decoration: none; }
.login-footer a:hover { color: <?= $themePrimary ?>; }

/* Mobiel: één kolom */
@media (max-width: 700px) {
    body { grid-template-columns: 1fr; }
    .login-visual { display: none; }
    .login-form-wrap { padding: 40px 24px; }
}
</style>
</head>
<body>

<!-- Linker paneel -->
<div class="login-visual">
    <div class="visual-logo">
        <?php if ($appLogo): ?>
        <img src="<?= htmlspecialchars($appLogo) ?>" alt="<?= htmlspecialchars($appName) ?>">
        <?php else: ?>
        <span class="visual-logo-icon">📦</span>
        <?php endif; ?>
        <span class="visual-app-name"><?= htmlspecialchars($appName) ?></span>
    </div>
    <p class="visual-tagline">
        Beheer al je IT assets op één centrale plek. Eenvoudig, overzichtelijk en snel.
    </p>
    <div class="visual-features">
        <div class="visual-feature">
            <div class="visual-feature-icon">💻</div>
            <span>Volledig asset overzicht per locatie</span>
        </div>
        <div class="visual-feature">
            <div class="visual-feature-icon">📊</div>
            <span>Rapporten en afschrijvingsoverzichten</span>
        </div>
        <div class="visual-feature">
            <div class="visual-feature-icon">🏷️</div>
            <span>Labels printen met QR codes</span>
        </div>
        <div class="visual-feature">
            <div class="visual-feature-icon">📚</div>
            <span>Kennisbank gekoppeld aan assets</span>
        </div>
        <div class="visual-feature">
            <div class="visual-feature-icon">📷</div>
            <span>QR scanner voor snel opzoeken</span>
        </div>
    </div>
</div>

<!-- Rechter paneel: formulier -->
<div class="login-form-wrap">
    <div class="login-form-inner">
        <h1>Welkom terug</h1>
        <p>Log in om verder te gaan met <?= htmlspecialchars($appName) ?>.</p>

        <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e): ?>
            <div>⚠️ <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="form-group">
                <label for="username">Gebruikersnaam of e-mail</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= htmlspecialchars($username) ?>"
                       placeholder="Voer je gebruikersnaam in"
                       autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Voer je wachtwoord in"
                       autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">Inloggen →</button>
        </form>

        <div class="login-footer">
            <p>© <?= date('Y') ?> <?= htmlspecialchars($appName) ?>. Alle rechten voorbehouden.</p>
        </div>
    </div>
</div>

</body>
</html>
