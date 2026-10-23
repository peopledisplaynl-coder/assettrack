<?php
/**
 * Database connection using PDO
 * AssetTrack - IT Asset Management System
 */

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/migrations.php';

// Config controleren. ASSETTRACK_CONFIG (environment) points to an alternative
// config file for tests, the demo and screenshots; on a normal server it is unset.
$configFile = getenv('ASSETTRACK_CONFIG') ?: __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    header('Location: /install/index.php');
    exit;
}

require_once $configFile;

// BASE_URL fallback
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

// Demo mode (fictional sample data, see tools/demo/). Off unless config.php sets it.
if (!defined('DEMO_MODE')) {
    define('DEMO_MODE', false);
}

$db = null;

function getDB(): PDO {
    global $db;
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db  = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $ip     = preg_replace('/[^0-9a-fA-F.:,]/', '', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $db->exec("SET @current_user_id = $userId");
            $db->exec("SET @client_ip = '$ip'");
            // Bring the database schema up to date after a deploy (see includes/migrations.php).
            if (!defined('ASSETTRACK_SKIP_AUTO_MIGRATE')) {
                autoMigrate($db);
            }
        } catch (PDOException $e) {
            error_log("Database verbindingsfout: " . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:30px;color:#dc2626;">
                <h2>Database verbindingsfout</h2>
                <p>Controleer <code>includes/config.php</code></p>
                <p><small>' . htmlspecialchars($e->getMessage()) . '</small></p>
            </div>');
        }
    }
    return $db;
}

function query(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function queryOne(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function execute(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function lastInsertId(): string    { return getDB()->lastInsertId(); }
function beginTransaction(): void  { getDB()->beginTransaction(); }
function commit(): void            { getDB()->commit(); }
function rollback(): void          { getDB()->rollBack(); }
function inTransaction(): bool     { return getDB()->inTransaction(); }
