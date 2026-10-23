<?php
/**
 * Automatic database migrations.
 *
 * Every file in /migrations named NNNN_description.php returns a callable
 * function(PDO $pdo): void. Pending migrations run automatically on the first
 * request after a deploy (called from getDB() in includes/db.php), so nobody
 * ever has to run SQL by hand in phpMyAdmin.
 *
 * Rules for migration files:
 *  - Never edit a migration that has been released; add a new one instead.
 *  - Migrations must be idempotent (safe to run on a database that already has
 *    the change), because older installations were sometimes updated by hand.
 *    Use the helpers migrationTableExists() / migrationColumnExists().
 *  - Strato rules apply: no DELIMITER, no triggers, no CREATE DATABASE.
 *  - Keep install/install.sql in step, so fresh installs get the same schema.
 */

const MIGRATIONS_DIR = __DIR__ . '/../migrations';

/** @return array<string,string> version => file path, sorted */
function migrationFiles(): array {
    $files = [];
    foreach (glob(MIGRATIONS_DIR . '/[0-9][0-9][0-9][0-9]_*.php') ?: [] as $file) {
        $files[substr(basename($file), 0, 4)] = $file;
    }
    ksort($files, SORT_STRING);
    return $files;
}

function migrationTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function migrationColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/** @return string[] versions already applied */
function appliedMigrations(PDO $pdo): array {
    try {
        return $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(20) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        return [];
    }
}

/**
 * Apply all pending migrations. Returns the list of applied versions.
 * Throws on the first failing migration (earlier ones stay applied).
 */
function runMigrations(PDO $pdo): array {
    $pending = array_diff_key(migrationFiles(), array_flip(appliedMigrations($pdo)));
    if (!$pending) {
        return [];
    }

    // Only one request at a time may migrate.
    $got = (int)$pdo->query("SELECT GET_LOCK('assettrack_migrations', 15)")->fetchColumn();
    if ($got !== 1) {
        return [];
    }
    $done = [];
    try {
        // Re-read inside the lock: another request may just have finished.
        $pending = array_diff_key(migrationFiles(), array_flip(appliedMigrations($pdo)));
        foreach ($pending as $version => $file) {
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("Migration $file does not return a callable");
            }
            $migration($pdo);
            $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (version, name) VALUES (?, ?)");
            $stmt->execute([(string)$version, basename($file, '.php')]);
            $done[] = (string)$version;
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('assettrack_migrations')");
    }
    return $done;
}

/**
 * Called once per request from getDB(). Cheap when there is nothing to do:
 * one SELECT plus a directory listing. Never breaks the request: failures are
 * logged and retried on the next request.
 */
function autoMigrate(PDO $pdo): void {
    try {
        $applied = runMigrations($pdo);
        if ($applied) {
            error_log('AssetTrack: database migrations applied: ' . implode(', ', $applied));
        }
    } catch (Throwable $e) {
        error_log('AssetTrack: database migration failed: ' . $e->getMessage());
    }
}
