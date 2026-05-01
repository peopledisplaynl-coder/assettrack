<?php
/**
 * AssetTrack Installer v3.1
 * Stap 1: Database verbinding
 * Stap 2: Beheerder account + organisatie
 * Stap 3: Weergave instellingen
 * Stap 4: Installatie voltooid
 */
session_start();

$configFile = __DIR__ . '/../includes/config.php';

// Al geïnstalleerd?
if (file_exists($configFile) && !isset($_GET['reinstall'])) { ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetTrack — Al geïnstalleerd</title>
    <style>
        body { font-family:system-ui,sans-serif; background:#f0f4f8; display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .box { background:white; padding:40px; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.1); max-width:480px; width:100%; text-align:center; }
        h2 { color:#1a2332; margin-bottom:12px; }
        p  { color:#64748b; margin-bottom:24px; line-height:1.6; }
        .btn { display:inline-block; padding:11px 22px; border-radius:8px; text-decoration:none; font-size:14px; margin:5px; font-weight:600; cursor:pointer; border:none; }
        .btn-danger   { background:#ef4444; color:white; }
        .btn-secondary{ background:#f1f5f9; color:#374151; }
    </style>
</head>
<body>
<div class="box">
    <div style="font-size:3rem;margin-bottom:16px;">✅</div>
    <h2>AssetTrack is al geïnstalleerd</h2>
    <p>Er is al een configuratiebestand aanwezig.<br>Wil je opnieuw installeren? De huidige configuratie wordt verwijderd.</p>
    <a href="index.php?reinstall=1" class="btn btn-danger"
       onclick="return confirm('Weet je zeker dat je opnieuw wilt installeren? Dit verwijdert de huidige configuratie.')">
        🔄 Opnieuw installeren
    </a>
    <a href="../index.php" class="btn btn-secondary">← Terug naar login</a>
</div>
</body>
</html>
<?php exit; }

// Verwijder config bij herinstallatie
if (isset($_GET['reinstall']) && file_exists($configFile)) {
    unlink($configFile);
    header('Location: index.php');
    exit;
}

$steps = [
    1 => ['title' => 'Database',    'icon' => '🗄️'],
    2 => ['title' => 'Beheerder',   'icon' => '👤'],
    3 => ['title' => 'Weergave',    'icon' => '🎨'],
    4 => ['title' => 'Gereed',      'icon' => '✅'],
];

$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$currentStep = max(1, min(4, $currentStep));
$errors   = [];
$warnings = [];
$success  = false;

// ── STAP 1: Database verbinding testen ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentStep === 1) {
    $dbHost  = trim($_POST['db_host']  ?? '');
    $dbName  = trim($_POST['db_name']  ?? '');
    $dbUser  = trim($_POST['db_user']  ?? '');
    $dbPass  = $_POST['db_pass']  ?? '';
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');

    if (empty($dbHost)) $errors[] = 'Database host is verplicht';
    if (empty($dbName)) $errors[] = 'Database naam is verplicht';
    if (empty($dbUser)) $errors[] = 'Database gebruiker is verplicht';

    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Controleer utf8mb4 ondersteuning
            $pdo->exec("SET NAMES utf8mb4");

            $_SESSION['install'] = [
                'db_host'  => $dbHost,
                'db_name'  => $dbName,
                'db_user'  => $dbUser,
                'db_pass'  => $dbPass,
                'base_url' => $baseUrl,
            ];
            header('Location: index.php?step=2');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database verbinding mislukt: ' . $e->getMessage();
            $errors[] = 'Controleer of de hostnaam, databasenaam en gebruiker correct zijn.';
        }
    }
}

// ── STAP 2: Beheerder + organisatienaam ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentStep === 2) {
    $orgName     = trim($_POST['org_name']         ?? '');
    $locName     = trim($_POST['loc_name']         ?? 'Hoofdlocatie');
    $fullName    = trim($_POST['full_name']        ?? '');
    $username    = trim($_POST['username']         ?? '');
    $email       = trim($_POST['email']            ?? '');
    $password    = $_POST['password']         ?? '';
    $passwordConf= $_POST['password_confirm'] ?? '';

    if (empty($orgName))     $errors[] = 'Organisatienaam is verplicht';
    if (empty($username))    $errors[] = 'Gebruikersnaam is verplicht';
    if (empty($email))       $errors[] = 'E-mailadres is verplicht';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ongeldig e-mailadres';
    if (empty($password))    $errors[] = 'Wachtwoord is verplicht';
    if (strlen($password) < 8) $errors[] = 'Wachtwoord minimaal 8 tekens';
    if ($password !== $passwordConf) $errors[] = 'Wachtwoorden komen niet overeen';

    if (empty($errors)) {
        $_SESSION['install']['org_name']  = $orgName;
        $_SESSION['install']['loc_name']  = $locName ?: 'Hoofdlocatie';
        $_SESSION['install']['full_name'] = $fullName ?: $username;
        $_SESSION['install']['username']  = $username;
        $_SESSION['install']['email']     = $email;
        $_SESSION['install']['password']  = $password;
        header('Location: index.php?step=3');
        exit;
    }
}

// ── STAP 3: Weergave instellingen ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentStep === 3) {
    $_SESSION['install']['app_name']    = trim($_POST['app_name']    ?? 'AssetTrack') ?: 'AssetTrack';
    $_SESSION['install']['theme_primary']   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_primary']   ?? '') ? $_POST['theme_primary']   : '#2563eb';
    $_SESSION['install']['theme_secondary'] = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_secondary'] ?? '') ? $_POST['theme_secondary'] : '#1a2332';
    $_SESSION['install']['font_family'] = preg_replace('/[^a-zA-Z0-9 ,]/', '', $_POST['font_family'] ?? 'DM Sans') ?: 'DM Sans';

    // Nu alles installeren
    $inst = $_SESSION['install'];

    // Config schrijven
    $baseUrl = $inst['base_url'] ?? '';
    $configContent  = "<?php\n";
    $configContent .= "define('DB_HOST', '" . addslashes($inst['db_host']) . "');\n";
    $configContent .= "define('DB_NAME', '" . addslashes($inst['db_name']) . "');\n";
    $configContent .= "define('DB_USER', '" . addslashes($inst['db_user']) . "');\n";
    $configContent .= "define('DB_PASS', '" . addslashes($inst['db_pass']) . "');\n";
    $configContent .= "define('BASE_URL', '" . addslashes($baseUrl) . "');\n";
    $configContent .= "define('INSTALLED', true);\n";

    if (file_put_contents($configFile, $configContent) === false) {
        $errors[] = 'Kon config.php niet aanmaken. Controleer schrijfrechten op de includes/ map.';
    } else {
        try {
            $dsn = "mysql:host=" . $inst['db_host'] . ";dbname=" . $inst['db_name'] . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $inst['db_user'], $inst['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET NAMES utf8mb4");

            // SQL uitvoeren
            $sql     = file_get_contents(__DIR__ . '/install.sql');
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($queries as $query) {
                if (!empty($query)) {
                    try { $pdo->exec($query); }
                    catch (PDOException $e) { $warnings[] = $e->getMessage(); }
                }
            }

            // Organisatie en locatie instellen
            $pdo->prepare("UPDATE organisations SET name = ? WHERE id = 1")->execute([$inst['org_name']]);
            $pdo->prepare("UPDATE locations SET name = ? WHERE id = 1")->execute([$inst['loc_name']]);
            $pdo->prepare("UPDATE companies SET name = ?, app_name = ?,
                            theme_primary = ?, theme_secondary = ?, font_family = ?
                            WHERE id = 1")
                ->execute([
                    $inst['org_name'],
                    $inst['app_name'],
                    $inst['theme_primary'],
                    $inst['theme_secondary'],
                    $inst['font_family'],
                ]);

            // Superadmin aanmaken
            $hash = password_hash($inst['password'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role, active, created_at)
                           VALUES (?, ?, ?, ?, 'superadmin', 1, NOW())")
                ->execute([$inst['full_name'], $inst['username'], $inst['email'], $hash]);

            $userId = (int)$pdo->lastInsertId();

            // Superadmin koppelen aan locatie 1
            $pdo->prepare("INSERT IGNORE INTO user_locations (user_id, location_id, can_view, can_edit)
                           VALUES (?, 1, 1, 1)")->execute([$userId]);

            // Mappen aanmaken
            $rootPath = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
            $dirs = [
                $rootPath . '/assets',
                $rootPath . '/assets/uploads',
                $rootPath . '/assets/uploads/asset_images',
                $rootPath . '/assets/uploads/location_logos',
                $rootPath . '/assets/uploads/template_images',
                $rootPath . '/assets/downloads',
                $rootPath . '/assets/img',
            ];
            $dirResults = [];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    $ok = @mkdir($dir, 0755, true);
                    $dirResults[] = ['path' => basename($dir), 'ok' => $ok || is_dir($dir)];
                } else {
                    $dirResults[] = ['path' => basename($dir), 'ok' => true];
                }
                if (is_dir($dir) && !file_exists($dir . '/index.php')) {
                    @file_put_contents($dir . '/index.php', '<?php // Beveiligd');
                }
            }
            foreach ($dirResults as $dr) {
                if (!$dr['ok']) {
                    $warnings[] = "Map '{$dr['path']}' kon niet aangemaakt worden — maak deze handmatig aan via Strato File Manager.";
                }
            }

            // Sessie opschonen
            unset($_SESSION['install']);

            $success     = true;
            $currentStep = 4;

        } catch (Exception $e) {
            $errors[] = 'Installatie mislukt: ' . $e->getMessage();
            if (file_exists($configFile)) unlink($configFile);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AssetTrack Installatie</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f0f4f8;
        color: #0f172a;
        min-height: 100vh;
    }
    .install-wrap {
        max-width: 640px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    .install-header {
        text-align: center;
        margin-bottom: 32px;
    }
    .install-header h1 {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1a2332;
        letter-spacing: -0.03em;
    }
    .install-header p { color: #64748b; margin-top: 6px; }

    /* Stappen indicator */
    .steps-bar {
        display: flex;
        align-items: center;
        margin-bottom: 32px;
    }
    .step-item {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
    }
    .step-item:last-child { flex: none; }
    .step-circle {
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        font-weight: 700;
        flex-shrink: 0;
        transition: all 0.2s;
    }
    .step-circle.done     { background: #10b981; color: white; }
    .step-circle.active   { background: #2563eb; color: white; box-shadow: 0 0 0 4px rgba(37,99,235,0.2); }
    .step-circle.pending  { background: #e2e8f0; color: #94a3b8; }
    .step-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #64748b;
    }
    .step-label.active { color: #2563eb; }
    .step-connector {
        flex: 1;
        height: 2px;
        background: #e2e8f0;
        margin: 0 8px;
    }
    .step-connector.done { background: #10b981; }

    /* Card */
    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .card-header {
        padding: 24px 28px 20px;
        border-bottom: 1px solid #f1f5f9;
    }
    .card-header h2 { font-size: 1.2rem; font-weight: 700; color: #1a2332; }
    .card-header p  { color: #64748b; font-size: 0.875rem; margin-top: 4px; }
    .card-body { padding: 24px 28px; }

    /* Forms */
    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.83rem;
        color: #374151;
        margin-bottom: 5px;
    }
    .form-group label span { color: #ef4444; margin-left: 2px; }
    .form-control {
        width: 100%;
        padding: 9px 13px;
        border: 1.5px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.93rem;
        font-family: inherit;
        color: #0f172a;
        background: white;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-control:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
    }
    .form-control::placeholder { color: #9ca3af; }
    small { display: block; margin-top: 4px; font-size: 0.78rem; color: #6b7280; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        font-family: inherit;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s;
    }
    .btn-primary {
        background: #2563eb;
        color: white;
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
        font-size: 1rem;
    }
    .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }

    /* Alerts */
    .alert {
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.875rem;
        border: 1px solid transparent;
    }
    .alert ul { padding-left: 18px; }
    .alert li { margin-bottom: 3px; }
    .alert-danger   { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .alert-warning  { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .alert-success  { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }

    /* Kleur picker rij */
    .color-row { display: flex; gap: 10px; align-items: center; }
    .color-row input[type="color"] {
        width: 46px; height: 38px;
        border: 1.5px solid #d1d5db;
        border-radius: 8px;
        padding: 2px;
        cursor: pointer;
        flex-shrink: 0;
    }
    .color-row .form-control { flex: 1; }

    /* Palet snelkeuze */
    .palette-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .palette-btn {
        display: flex; align-items: center; gap: 5px;
        padding: 4px 10px;
        border: 1.5px solid #e5e7eb;
        border-radius: 20px;
        font-size: 0.75rem;
        background: white;
        cursor: pointer;
        transition: border-color 0.15s;
    }
    .palette-btn:hover { border-color: #2563eb; }
    .palette-dot { width: 11px; height: 11px; border-radius: 50%; }

    /* Succes scherm */
    .success-screen { text-align: center; padding: 32px 28px; }
    .success-icon { font-size: 4rem; margin-bottom: 16px; }
    .success-screen h2 { font-size: 1.5rem; font-weight: 800; color: #1a2332; margin-bottom: 10px; }
    .success-screen p  { color: #64748b; line-height: 1.7; margin-bottom: 8px; }
    .success-info {
        background: #f8fafc;
        border-radius: 10px;
        padding: 16px;
        margin: 20px 0;
        text-align: left;
        font-size: 0.875rem;
    }
    .success-info div { display: flex; gap: 8px; margin-bottom: 6px; color: #374151; }
    .success-info div:last-child { margin-bottom: 0; }
    .success-info span { color: #10b981; flex-shrink: 0; }
    .btn-go {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 16px;
        background: #2563eb;
        color: white;
        padding: 13px 32px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 1rem;
        font-weight: 700;
        transition: all 0.15s;
    }
    .btn-go:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(37,99,235,0.4); }
</style>
</head>
<body>
<div class="install-wrap">

    <!-- Header -->
    <div class="install-header">
        <h1>📦 AssetTrack Installatie</h1>
        <p>Volg de stappen om AssetTrack te installeren op jouw server.</p>
    </div>

    <!-- Stappen balk -->
    <div class="steps-bar">
        <?php foreach ($steps as $num => $step):
            $isDone   = $num < $currentStep || $success;
            $isActive = $num === $currentStep && !$success;
            $circleClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
        ?>
        <div class="step-item">
            <div class="step-circle <?= $circleClass ?>">
                <?= $isDone ? '✓' : $step['icon'] ?>
            </div>
            <div class="step-label <?= $isActive ? 'active' : '' ?>">
                <?= $step['title'] ?>
            </div>
        </div>
        <?php if ($num < count($steps)): ?>
        <div class="step-connector <?= $isDone ? 'done' : '' ?>"></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Fouten -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>❌ Fout(en):</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- Waarschuwingen -->
    <?php if (!empty($warnings)): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Waarschuwingen (installatie doorgegaan):</strong>
        <ul><?php foreach ($warnings as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- ── STAP 4: SUCCES ───────────────────────────────────────────── -->
    <?php if ($success || $currentStep === 4): ?>
    <div class="card">
        <div class="success-screen">
            <div class="success-icon">🎉</div>
            <h2>AssetTrack is geïnstalleerd!</h2>
            <p>Je kunt nu inloggen met het beheerder account dat je zojuist aangemaakt hebt.</p>
            <div class="success-info">
                <div><span>✓</span> Database tabellen aangemaakt</div>
                <div><span>✓</span> Standaard permissies ingesteld</div>
                <div><span>✓</span> Superadmin account aangemaakt</div>
                <div><span>✓</span> Organisatie en locatie ingesteld</div>
                <div><span>✓</span> Thema en lettertype geconfigureerd</div>
                <div><span>✓</span> Upload mappen aangemaakt</div>
            </div>
            <?php
                $installedBaseUrl = '';
                if (file_exists($configFile)) {
                    include $configFile;
                    $installedBaseUrl = defined('BASE_URL') ? BASE_URL : '';
                }
            ?>
            <p style="font-size:0.82rem;color:#94a3b8;">
                ⚠️ Verwijder of beveilig de <code>/install/</code> map na installatie.
            </p>
            <a href="<?= $installedBaseUrl ?>/index.php" class="btn-go">
                Ga naar AssetTrack →
            </a>
        </div>
    </div>

    <!-- ── STAP 1: Database ─────────────────────────────────────────── -->
    <?php elseif ($currentStep === 1): ?>
    <div class="card">
        <div class="card-header">
            <h2>🗄️ Database verbinding</h2>
            <p>Vul de database gegevens in uit het Strato controlepaneel.</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Database host <span>*</span></label>
                    <input type="text" name="db_host" class="form-control" required
                           value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>"
                           placeholder="database-XXXXXXXX.webspace-host.com">
                    <small>Te vinden in het Strato controlepaneel onder Database.</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Database naam <span>*</span></label>
                        <input type="text" name="db_name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>"
                               placeholder="dbs12345678">
                    </div>
                    <div class="form-group">
                        <label>Database gebruiker <span>*</span></label>
                        <input type="text" name="db_user" class="form-control" required
                               value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>"
                               placeholder="dbu1234567">
                    </div>
                </div>
                <div class="form-group">
                    <label>Database wachtwoord</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:24px;">
                    <label>Installatiemap (BASE_URL)</label>
                    <input type="text" name="base_url" class="form-control"
                           value="<?= htmlspecialchars($_POST['base_url'] ?? '/assettrack') ?>"
                           placeholder="/assettrack">
                    <small>De map waar AssetTrack staat, bijv. <strong>/assettrack</strong>. Laat leeg als subdomein zonder submap.</small>
                </div>
                <button type="submit" class="btn btn-primary">
                    Verbinding testen &amp; doorgaan →
                </button>
            </form>
        </div>
    </div>

    <!-- ── STAP 2: Beheerder ────────────────────────────────────────── -->
    <?php elseif ($currentStep === 2): ?>
    <div class="card">
        <div class="card-header">
            <h2>👤 Organisatie & Beheerder</h2>
            <p>Stel de naam van je organisatie in en maak een beheerder account aan.</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <div style="background:#eff6ff;border-radius:10px;padding:16px;margin-bottom:20px;border:1px solid #bfdbfe;">
                    <h3 style="font-size:0.9rem;color:#1e40af;margin:0 0 12px;">🏢 Organisatie</h3>
                    <div class="form-row">
                        <div class="form-group" style="margin:0;">
                            <label>Organisatienaam <span>*</span></label>
                            <input type="text" name="org_name" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['org_name'] ?? '') ?>"
                                   placeholder="bijv. Basisschool De Zon">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Locatienaam</label>
                            <input type="text" name="loc_name" class="form-control"
                                   value="<?= htmlspecialchars($_POST['loc_name'] ?? 'Hoofdlocatie') ?>"
                                   placeholder="Hoofdlocatie">
                            <small>Naam van de eerste locatie.</small>
                        </div>
                    </div>
                </div>

                <div style="background:#f8fafc;border-radius:10px;padding:16px;border:1px solid #e2e8f0;">
                    <h3 style="font-size:0.9rem;color:#374151;margin:0 0 12px;">🔐 Superadmin account</h3>
                    <div class="form-group">
                        <label>Volledige naam</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               placeholder="Optioneel">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gebruikersnaam <span>*</span></label>
                            <input type="text" name="username" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   placeholder="admin">
                        </div>
                        <div class="form-group">
                            <label>E-mailadres <span>*</span></label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="admin@bedrijf.nl">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Wachtwoord <span>*</span></label>
                            <input type="password" name="password" class="form-control"
                                   required minlength="8" placeholder="Minimaal 8 tekens">
                        </div>
                        <div class="form-group">
                            <label>Wachtwoord bevestigen <span>*</span></label>
                            <input type="password" name="password_confirm" class="form-control"
                                   required minlength="8" placeholder="Herhaal wachtwoord">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:20px;">
                    Doorgaan naar weergave →
                </button>
            </form>
        </div>
    </div>

    <!-- ── STAP 3: Weergave ─────────────────────────────────────────── -->
    <?php elseif ($currentStep === 3): ?>
    <div class="card">
        <div class="card-header">
            <h2>🎨 Weergave instellingen</h2>
            <p>Pas de naam, kleur en het lettertype aan. Je kunt dit later altijd wijzigen.</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Applicatienaam</label>
                    <input type="text" name="app_name" class="form-control"
                           value="<?= htmlspecialchars($_POST['app_name'] ?? 'AssetTrack') ?>"
                           placeholder="AssetTrack">
                    <small>Wordt getoond in de header en browser tab.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Primaire kleur (knoppen)</label>
                        <div class="color-row">
                            <input type="color" id="cp" value="#2563eb"
                                   oninput="document.getElementById('ct').value=this.value">
                            <input type="text" name="theme_primary" id="ct" class="form-control"
                                   value="#2563eb" placeholder="#2563eb"
                                   oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))document.getElementById('cp').value=this.value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Header achtergrond kleur</label>
                        <div class="color-row">
                            <input type="color" id="cs" value="#1a2332"
                                   oninput="document.getElementById('cst').value=this.value">
                            <input type="text" name="theme_secondary" id="cst" class="form-control"
                                   value="#1a2332" placeholder="#1a2332"
                                   oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))document.getElementById('cs').value=this.value">
                        </div>
                    </div>
                </div>

                <!-- Snelkeuze paletten -->
                <div class="form-group">
                    <label>Snelkeuze</label>
                    <div class="palette-grid">
                        <?php
                        $palettes = [
                            ['Blauw',   '#2563eb','#1a2332'],
                            ['Groen',   '#059669','#1a2d27'],
                            ['Paars',   '#7c3aed','#1e1a2e'],
                            ['Rood',    '#dc2626','#2d1a1a'],
                            ['Oranje',  '#ea580c','#2d1f0d'],
                            ['Teal',    '#0891b2','#0c1f2d'],
                            ['Donker',  '#334155','#1e293b'],
                            ['Rose',    '#e11d48','#2d1a20'],
                        ];
                        foreach ($palettes as [$name, $p, $s]):
                        ?>
                        <button type="button" class="palette-btn"
                                onclick="setPalette('<?= $p ?>','<?= $s ?>')">
                            <span class="palette-dot" style="background:<?= $p ?>;"></span>
                            <span class="palette-dot" style="background:<?= $s ?>;"></span>
                            <?= $name ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Lettertype</label>
                    <select name="font_family" class="form-control" id="fontSel"
                            onchange="previewFont(this.value)">
                        <?php
                        $fonts = ['DM Sans','Inter','Plus Jakarta Sans','Outfit','Nunito','Source Sans 3','Figtree'];
                        foreach ($fonts as $f):
                        ?>
                        <option value="<?= $f ?>" <?= $f==='DM Sans'?'selected':'' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="fontDemo"
                         style="margin-top:8px;padding:10px 14px;background:#f8fafc;
                                border-radius:6px;border:1px solid #e5e7eb;font-size:1rem;
                                font-family:'DM Sans',sans-serif;">
                        De snelle bruine vos springt over de luie hond. 0123456789
                    </div>
                </div>

                <!-- Mini preview -->
                <div style="margin:20px 0;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
                    <div id="prevHeader" style="background:#1a2332;padding:10px 16px;
                         display:flex;align-items:center;gap:12px;">
                        <span id="prevName" style="font-weight:700;color:white;font-size:1rem;">📦 AssetTrack</span>
                        <div style="margin-left:auto;display:flex;gap:4px;">
                            <?php foreach (['🏠','💻','📊','⚙️'] as $ico): ?>
                            <span style="color:rgba(255,255,255,0.6);font-size:0.75rem;
                                         padding:4px 8px;border-radius:5px;"><?= $ico ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="prevBar" style="height:3px;background:#2563eb;"></div>
                    <div style="padding:12px 16px;background:#f0f4f8;">
                        <button id="prevBtn" style="background:#2563eb;color:white;border:none;
                                padding:6px 14px;border-radius:7px;font-size:0.82rem;font-weight:600;">
                            + Asset toevoegen
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    🚀 Installatie voltooien
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function setPalette(p, s) {
    document.getElementById('cp').value  = p;
    document.getElementById('ct').value  = p;
    document.getElementById('cs').value  = s;
    document.getElementById('cst').value = s;
    updatePreview();
}
function updatePreview() {
    var p = document.getElementById('cp').value;
    var s = document.getElementById('cs').value;
    var name = document.getElementById('prevName');
    var appInput = document.querySelector('input[name="app_name"]');
    if (name && appInput) name.textContent = '📦 ' + (appInput.value || 'AssetTrack');
    document.getElementById('prevHeader').style.background = s;
    document.getElementById('prevBar').style.background    = p;
    document.getElementById('prevBtn').style.background    = p;
}
function previewFont(f) {
    var slug = f.replace(/ /g, '+');
    var l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = 'https://fonts.googleapis.com/css2?family=' + slug + ':wght@400;700&display=swap';
    document.head.appendChild(l);
    setTimeout(function() {
        document.getElementById('fontDemo').style.fontFamily = "'" + f + "',sans-serif";
    }, 500);
}
// Live preview updates
document.addEventListener('input', function(e) {
    if (e.target.name === 'app_name'        ||
        e.target.id === 'cp' || e.target.id === 'ct' ||
        e.target.id === 'cs' || e.target.id === 'cst') {
        updatePreview();
    }
});
</script>

</body>
</html>
