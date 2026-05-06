<?php
/**
 * AssetTrack Backup Module
 * Ondersteunt: database-only, bestanden-only, of volledige backup
 * Platformonafhankelijk — werkt op Strato, TransIP, Hostnet, etc.
 * Alleen toegankelijk voor superadmin
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

if (getRole() !== 'superadmin') {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#dc2626;">Geen toegang</h2>
        <p>Alleen superadmin kan backups maken.</p>
        <a href="' . BASE_URL . '/modules/settings/">Terug</a>
    </div>');
}

// ── Configuratie ──────────────────────────────────────────────────
$rootPath    = realpath(__DIR__ . '/../../') ?: dirname(dirname(__DIR__));
$backupType  = $_POST['backup_type'] ?? $_GET['type'] ?? '';
$maxExecTime = 300; // 5 minuten
@set_time_limit($maxExecTime);
@ini_set('memory_limit', '256M');

// ════════════════════════════════════════════════════════════════════
// DATABASE DUMP — pure PHP via PDO, geen mysqldump nodig
// ════════════════════════════════════════════════════════════════════
function generateSqlDump(PDO $pdo, string $dbName): string {
    $sql  = "-- AssetTrack Database Backup\n";
    $sql .= "-- Gegenereerd op: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: $dbName\n";
    $sql .= "-- Compatibel met MySQL 5.7+, MariaDB 10.x+\n";
    $sql .= "-- Herstel: importeer dit bestand via phpMyAdmin of mysql -u user -p db < backup.sql\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    // Haal alle tabellen op
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $sql .= "-- ─────────────────────────────────────────────\n";
        $sql .= "-- Tabel: `$table`\n";
        $sql .= "-- ─────────────────────────────────────────────\n\n";

        // DROP + CREATE TABLE
        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? '';

        // Verwijder AUTO_INCREMENT waarde voor portabiliteit
        $createSql = preg_replace('/AUTO_INCREMENT=\d+\s?/', '', $createSql);

        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createSql . ";\n\n";

        // Data dumpen in batches van 500 rijen
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($count == 0) {
            $sql .= "-- (geen data)\n\n";
            continue;
        }

        $batchSize = 500;
        $offset    = 0;

        while ($offset < $count) {
            $rows = $pdo->query("SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset")
                        ->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) break;

            $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql .= "INSERT INTO `$table` ($columns) VALUES\n";

            $values = [];
            foreach ($rows as $row) {
                $escaped = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote((string)$v);
                }, array_values($row));
                $values[] = '(' . implode(', ', $escaped) . ')';
            }

            $sql .= implode(",\n", $values) . ";\n\n";
            $offset += $batchSize;
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "-- Einde backup\n";
    return $sql;
}

// ════════════════════════════════════════════════════════════════════
// BESTAND BACKUP — ZIP van uploads en config
// ════════════════════════════════════════════════════════════════════
function addDirToZip(ZipArchive $zip, string $dir, string $zipBase, array $exclude = []): int {
    $count = 0;
    if (!is_dir($dir)) return 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $realPath = $file->getRealPath();
            // Sla tijdelijke en grote bestanden over
            $skip = false;
            foreach ($exclude as $ex) {
                if (strpos($realPath, $ex) !== false) { $skip = true; break; }
            }
            if ($skip) continue;
            $relativePath = $zipBase . str_replace($dir . DIRECTORY_SEPARATOR, '', $realPath);
            $relativePath = str_replace('\\', '/', $relativePath);
            $zip->addFile($realPath, $relativePath);
            $count++;
        }
    }
    return $count;
}

// ════════════════════════════════════════════════════════════════════
// BACKUP UITVOEREN
// ════════════════════════════════════════════════════════════════════
if ($backupType && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {

    $timestamp  = date('Y-m-d_H-i-s');
    $dbName     = DB_NAME;
    $errors     = [];

    // ── DATABASE backup ───────────────────────────────────────────
    if ($backupType === 'database' || $backupType === 'full') {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $sqlDump = generateSqlDump($pdo, $dbName);

            if ($backupType === 'database') {
                // Stuur direct als .sql.gz download
                $filename = "assettrack_db_{$timestamp}.sql";
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($sqlDump));
                echo $sqlDump;
                exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Database dump mislukt: ' . $e->getMessage();
        }
    }

    // ── BESTANDEN backup ──────────────────────────────────────────
    if ($backupType === 'files' || $backupType === 'full') {
        if (!class_exists('ZipArchive')) {
            $errors[] = 'ZipArchive niet beschikbaar op deze server.';
        } else {
            $tmpFile  = sys_get_temp_dir() . "/assettrack_backup_{$timestamp}.zip";
            $zip      = new ZipArchive();
            $zipOpened = $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($zipOpened !== true) {
                $errors[] = 'Kon ZIP bestand niet aanmaken (code: ' . $zipOpened . ').';
            } else {
                $exclude = ['.git', 'node_modules', '__pycache__'];

                if ($backupType === 'full') {
                    // Voeg SQL dump toe in de ZIP
                    $zip->addFromString("database/assettrack_db_{$timestamp}.sql", $sqlDump ?? '');

                    // Herstel instructies
                    $instructions = "# AssetTrack Volledige Backup\n\n"
                        . "Gemaakt op: " . date('d-m-Y H:i:s') . "\n\n"
                        . "## Inhoud van deze backup\n"
                        . "- `database/` — SQL dump van de volledige database\n"
                        . "- `files/` — Alle bestanden (uploads, config, assets)\n\n"
                        . "## Herstel instructies\n\n"
                        . "### Stap 1 — Database herstellen\n"
                        . "1. Maak een nieuwe database aan op de nieuwe server\n"
                        . "2. Importeer `database/assettrack_db_{$timestamp}.sql` via phpMyAdmin\n"
                        . "   of via: `mysql -u gebruiker -p databasenaam < assettrack_db_{$timestamp}.sql`\n\n"
                        . "### Stap 2 — Bestanden herstellen\n"
                        . "1. Upload de inhoud van `files/` naar de webserver\n"
                        . "2. Pas `includes/config.php` aan met de nieuwe database gegevens:\n"
                        . "   - DB_HOST: nieuwe database hostnaam\n"
                        . "   - DB_NAME: nieuwe databasenaam\n"
                        . "   - DB_USER: nieuwe gebruikersnaam\n"
                        . "   - DB_PASS: nieuwe wachtwoord\n"
                        . "   - BASE_URL: nieuwe URL pad\n\n"
                        . "### Stap 3 — Controleren\n"
                        . "1. Open de website in de browser\n"
                        . "2. Log in met je bestaande gegevens\n"
                        . "3. Controleer of assets en foto's correct laden\n\n"
                        . "## Systeemvereisten nieuwe server\n"
                        . "- PHP 8.x\n"
                        . "- MySQL 5.7+ of MariaDB 10.x+\n"
                        . "- ZipArchive PHP extensie\n"
                        . "- Schrijfrechten op assets/uploads/\n";

                    $zip->addFromString('HERSTEL_INSTRUCTIES.md', $instructions);
                }

                // Voeg ALLE mappen en bestanden toe
                $foldersToBackup = [
                    'assets'    => $rootPath . '/assets',
                    'includes'  => $rootPath . '/includes',
                    'install'   => $rootPath . '/install',
                    'modules'   => $rootPath . '/modules',
                    'templates' => $rootPath . '/templates',
                ];

                foreach ($foldersToBackup as $zipDir => $srcDir) {
                    addDirToZip($zip, $srcDir, 'files/' . $zipDir . '/', $exclude);
                }

                // Root PHP bestanden
                $rootFiles = glob($rootPath . '/*.php');
                $rootFiles = array_merge($rootFiles, glob($rootPath . '/*.json'));
                $rootFiles = array_merge($rootFiles, glob($rootPath . '/*.txt'));
                foreach ($rootFiles as $file) {
                    if (is_file($file)) {
                        $zip->addFile($file, 'files/' . basename($file));
                    }
                }

                $zip->close();

                // Download
                $filename = $backupType === 'full'
                    ? "assettrack_volledig_{$timestamp}.zip"
                    : "assettrack_bestanden_{$timestamp}.zip";

                if (file_exists($tmpFile)) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($tmpFile));
                    readfile($tmpFile);
                    @unlink($tmpFile);
                    exit;
                } else {
                    $errors[] = 'ZIP bestand kon niet worden aangemaakt.';
                }
            }
        }
    }
}

// ════════════════════════════════════════════════════════════════════
// PAGINA
// ════════════════════════════════════════════════════════════════════

// Controleer systeemvereisten
$hasZip     = class_exists('ZipArchive');
$hasPdo     = class_exists('PDO');
$uploadsDir = $rootPath . '/assets/uploads';
$uploadsSize = 0;
if (is_dir($uploadsDir)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir));
    foreach ($iter as $f) { if ($f->isFile()) $uploadsSize += $f->getSize(); }
}
$uploadsMB = round($uploadsSize / 1024 / 1024, 1);

// Database grootte schatten
$dbSize = 0;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                   DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbRow = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
                          FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch();
    $dbSize = $dbRow['mb'] ?? 0;
    $tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
} catch (Exception $e) {
    $dbSize = '?'; $tableCount = '?';
}

$pageTitle = 'Backup & Herstel';
include __DIR__ . '/../../templates/header.php';

$errors = $errors ?? [];
?>

<div class="page-header">
    <h1>💾 Backup & Herstel</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div>❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Systeem info -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:24px;">
    <div class="card" style="margin:0;">
        <div class="card-body" style="text-align:center;">
            <div style="font-size:1.8rem;font-weight:700;color:#1a2332;"><?= $dbSize ?> MB</div>
            <div style="font-size:0.8rem;color:#64748b;">Database grootte</div>
            <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;"><?= $tableCount ?> tabellen</div>
        </div>
    </div>
    <div class="card" style="margin:0;">
        <div class="card-body" style="text-align:center;">
            <div style="font-size:1.8rem;font-weight:700;color:#1a2332;"><?= $uploadsMB ?> MB</div>
            <div style="font-size:0.8rem;color:#64748b;">Uploads (foto's/logo's)</div>
        </div>
    </div>
    <div class="card" style="margin:0;">
        <div class="card-body" style="text-align:center;">
            <div style="font-size:1.8rem;"><?= $hasZip ? '✅' : '❌' ?></div>
            <div style="font-size:0.8rem;color:#64748b;">ZipArchive</div>
        </div>
    </div>
    <div class="card" style="margin:0;">
        <div class="card-body" style="text-align:center;">
            <div style="font-size:1.8rem;"><?= $hasPdo ? '✅' : '❌' ?></div>
            <div style="font-size:0.8rem;color:#64748b;">PDO MySQL</div>
        </div>
    </div>
</div>

<!-- Backup opties -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">

    <!-- Database backup -->
    <div class="card" style="margin:0;">
        <div class="card-body">
            <div style="font-size:2rem;margin-bottom:10px;">🗄️</div>
            <h3 style="margin:0 0 8px;color:#1a2332;">Database backup</h3>
            <p style="color:#64748b;font-size:0.875rem;margin-bottom:16px;line-height:1.6;">
                Exporteert alle tabellen en data als <code>.sql</code> bestand.
                Importeerbaar via phpMyAdmin op elke MySQL/MariaDB server.
            </p>
            <div style="background:#f8fafc;border-radius:8px;padding:10px;margin-bottom:16px;font-size:0.8rem;color:#64748b;">
                <div>📦 Formaat: SQL bestand</div>
                <div>📏 Geschatte grootte: ~<?= $dbSize ?> MB</div>
                <div>⏱️ Duur: seconden tot minuten</div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="backup_type" value="database">
                <button type="submit" class="btn btn-primary" style="width:100%;"
                        <?= !$hasPdo ? 'disabled' : '' ?>>
                    ⬇️ Database downloaden
                </button>
            </form>
        </div>
    </div>

    <!-- Bestanden backup -->
    <div class="card" style="margin:0;">
        <div class="card-body">
            <div style="font-size:2rem;margin-bottom:10px;">📁</div>
            <h3 style="margin:0 0 8px;color:#1a2332;">Bestanden backup</h3>
            <p style="color:#64748b;font-size:0.875rem;margin-bottom:16px;line-height:1.6;">
                Maakt een ZIP van alle bestanden: PHP, uploads, templates,
                modules en configuratie. Volledig herstelbaar op elke server.
            </p>
            <div style="background:#f8fafc;border-radius:8px;padding:10px;margin-bottom:16px;font-size:0.8rem;color:#64748b;">
                <div>📦 Formaat: ZIP bestand</div>
                <div>📏 Bevat: alle mappen, PHP bestanden en uploads</div>
                <div>⏱️ Duur: afhankelijk van aantal foto's</div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="backup_type" value="files">
                <button type="submit" class="btn btn-primary" style="width:100%;"
                        <?= !$hasZip ? 'disabled' : '' ?>>
                    ⬇️ Bestanden downloaden
                </button>
            </form>
            <?php if (!$hasZip): ?>
            <p style="color:#dc2626;font-size:0.78rem;margin-top:8px;">
                ⚠️ ZipArchive niet beschikbaar op deze server.
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Volledige backup -->
    <div class="card" style="margin:0;border:2px solid #2563eb;">
        <div class="card-body">
            <div style="font-size:2rem;margin-bottom:10px;">🔒</div>
            <h3 style="margin:0 0 8px;color:#1a2332;">Volledige backup</h3>
            <p style="color:#64748b;font-size:0.875rem;margin-bottom:16px;line-height:1.6;">
                Database + bestanden + herstel instructies in één ZIP.
                Compleet pakket voor verhuizing naar een andere server.
            </p>
            <div style="background:#eff6ff;border-radius:8px;padding:10px;margin-bottom:16px;font-size:0.8rem;color:#1e40af;">
                <div>📦 Formaat: ZIP met SQL + bestanden</div>
                <div>📏 Geschatte grootte: ~<?= round($dbSize + $uploadsMB, 1) ?> MB</div>
                <div>📋 Inclusief herstel instructies</div>
                <div>⏱️ Duur: kan enkele minuten duren</div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="backup_type" value="full">
                <button type="submit" class="btn btn-primary" style="width:100%;background:#2563eb;"
                        <?= (!$hasZip || !$hasPdo) ? 'disabled' : '' ?>
                        onclick="return confirm('Volledige backup maken? Dit kan enkele minuten duren.')">
                    ⬇️ Volledige backup downloaden
                </button>
            </form>
        </div>
    </div>

</div>

<!-- Herstel info -->
<div class="card" style="margin-top:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;">📋 Herstel instructies</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
            <div>
                <h4 style="color:#2563eb;margin-bottom:8px;">Database herstellen</h4>
                <ol style="padding-left:18px;color:#374151;font-size:0.875rem;line-height:1.8;">
                    <li>Maak een nieuwe MySQL database aan op de doelserver</li>
                    <li>Open phpMyAdmin en selecteer de nieuwe database</li>
                    <li>Klik op <strong>Importeren</strong></li>
                    <li>Upload het <code>.sql</code> bestand</li>
                    <li>Klik op <strong>Uitvoeren</strong></li>
                </ol>
            </div>
            <div>
                <h4 style="color:#2563eb;margin-bottom:8px;">Verhuizen naar andere server</h4>
                <ol style="padding-left:18px;color:#374151;font-size:0.875rem;line-height:1.8;">
                    <li>Upload alle AssetTrack bestanden naar de nieuwe server</li>
                    <li>Importeer de database (zie links)</li>
                    <li>Pas <code>includes/config.php</code> aan:<br>
                        <code style="font-size:0.78rem;background:#f8fafc;padding:1px 4px;border-radius:3px;">DB_HOST, DB_NAME, DB_USER, DB_PASS, BASE_URL</code>
                    </li>
                    <li>Upload de bestanden uit de backup ZIP</li>
                    <li>Controleer schrijfrechten op <code>assets/uploads/</code></li>
                </ol>
            </div>
        </div>
        <div class="alert alert-warning" style="margin-top:16px;margin-bottom:0;">
            ⚠️ <strong>Let op:</strong> De backup bevat <code>config.php</code> met database wachtwoorden.
            Bewaar backups op een veilige locatie en deel ze nooit publiekelijk.
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
