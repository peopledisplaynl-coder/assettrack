<?php
function freshTestDb(): PDO {
    $pdo = getDB();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec("DROP TABLE `$t`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    return $pdo;
}
function installSchema(PDO $pdo): void {
    $sql = file_get_contents(dirname(__DIR__, 2) . '/install/install.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) $pdo->exec($q);
}

test('migration files are numbered uniquely and return callables', function () {
    $files = migrationFiles();
    assertTrue(count($files) >= 3, 'expected at least 3 migrations');
    foreach ($files as $file) assertTrue(is_callable(require $file), basename($file));
});

test('fresh install: migrations only get recorded, schema unchanged', function () {
    $pdo = freshTestDb();
    installSchema($pdo);
    $applied = runMigrations($pdo);
    assertSame(array_map('strval', array_keys(migrationFiles())), $applied);
    assertSame([], runMigrations($pdo), 'second run must do nothing');
}, true);

test('old installation (before 2026-09-11) gets all missing columns and tables', function () {
    $pdo = freshTestDb();
    installSchema($pdo);
    $pdo->exec('DROP TABLE remember_tokens');
    $pdo->exec('ALTER TABLE users DROP COLUMN avatar_filename');
    $pdo->exec('ALTER TABLE companies DROP COLUMN scan_base_url');
    runMigrations($pdo);
    assertTrue(migrationTableExists($pdo, 'remember_tokens'));
    assertTrue(migrationColumnExists($pdo, 'users', 'avatar_filename'));
    assertTrue(migrationColumnExists($pdo, 'companies', 'scan_base_url'));
}, true);

test('installation where the old SQL files were run by hand is accepted', function () {
    $pdo = freshTestDb();
    installSchema($pdo);   // has everything already, but no schema_migrations table
    $pdo->exec('DROP TABLE IF EXISTS schema_migrations');
    runMigrations($pdo);
    $n = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    assertSame(count(migrationFiles()), $n);
}, true);

test('install.sql matches the migrated schema (columns of every table)', function () {
    $pdo = freshTestDb();
    installSchema($pdo);
    runMigrations($pdo);
    $cols = fn() => $pdo->query("SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME) FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
    $fresh = $cols();
    // Rebuild from the oldest known schema and migrate up; must give the same columns.
    $pdo = freshTestDb();
    installSchema($pdo);
    $pdo->exec('DROP TABLE remember_tokens');
    $pdo->exec('ALTER TABLE users DROP COLUMN avatar_filename');
    $pdo->exec('ALTER TABLE companies DROP COLUMN scan_base_url');
    runMigrations($pdo);
    assertSame($fresh, $cols());
}, true);
