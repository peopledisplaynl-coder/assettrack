<?php
/**
 * Minimal PHP test runner (no Composer needed).
 *
 *   php tests/php/run.php
 *
 * Unit tests always run. Database tests run when a MariaDB/MySQL test database
 * is reachable (start it with: tools/dev/stack.sh db). They use the database
 * "assettrack_test" and wipe it.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
$root = dirname(__DIR__, 2);

// Config for the test database (never includes/config.php).
$tmp = $root . '/tests/.tmp';
@mkdir($tmp, 0777, true);
$port = getenv('AT_DB_PORT') ?: '13306';
file_put_contents("$tmp/config-test.php", "<?php\n"
    . "define('DB_HOST', '" . (getenv('AT_DB_HOST') ?: '127.0.0.1') . ";port=$port');\n"
    . "define('DB_NAME', 'assettrack_test');\ndefine('DB_USER', 'assettrack');\ndefine('DB_PASS', 'assettrack');\n"
    . "define('BASE_URL', '');\ndefine('INSTALLED', true);\n");
putenv("ASSETTRACK_CONFIG=$tmp/config-test.php");
define('ASSETTRACK_SKIP_AUTO_MIGRATE', true);
require $root . '/includes/functions.php';

$GLOBALS['__tests'] = [];
function test(string $name, callable $fn, bool $needsDb = false): void { $GLOBALS['__tests'][] = [$name, $fn, $needsDb]; }
function assertSame($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        throw new Exception(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function assertTrue($cond, string $msg = 'expected true'): void { if (!$cond) throw new Exception($msg); }

foreach (glob(__DIR__ . '/*Test.php') as $file) require $file;

$dbAvailable = false;
try {
    $probe = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [PDO::ATTR_TIMEOUT => 2]);
    $dbAvailable = true;
} catch (Throwable $e) {
}

$pass = $fail = $skip = 0;
foreach ($GLOBALS['__tests'] as [$name, $fn, $needsDb]) {
    if ($needsDb && !$dbAvailable) { $skip++; echo "  SKIP  $name (no test database)\n"; continue; }
    try { $fn(); $pass++; echo "  ok    $name\n"; }
    catch (Throwable $e) { $fail++; echo "  FAIL  $name\n        " . $e->getMessage() . "\n"; }
}
echo "\n$pass passed, $fail failed, $skip skipped\n";
exit($fail ? 1 : 0);
