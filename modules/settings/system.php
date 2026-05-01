<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
if (getRole() !== 'superadmin') {
    die('Alleen superadmin heeft toegang tot systeeminstellingen.');
}

$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;
$offset     = ($page - 1) * $perPage;
$filterUser = (int)($_GET['user'] ?? 0);
$filterDate = $_GET['date'] ?? '';

$sql    = "SELECT al.*, u.username FROM audit_log al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
$params = [];
if ($filterUser) { $sql .= " AND al.user_id = ?"; $params[] = $filterUser; }
if ($filterDate) { $sql .= " AND DATE(al.created_at) = ?"; $params[] = $filterDate; }

$totalRows = queryOne("SELECT COUNT(*) as c FROM audit_log al WHERE 1=1" .
    ($filterUser ? " AND al.user_id = $filterUser" : '') .
    ($filterDate ? " AND DATE(al.created_at) = '$filterDate'" : ''))['c'] ?? 0;

$sql .= " ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset";
$logs = query($sql, $params);
$users = query("SELECT id, username FROM users ORDER BY username");

// Systeeminfo
$phpVersion   = PHP_VERSION;
$mysqlVersion = queryOne("SELECT VERSION() as v")['v'] ?? 'onbekend';
$assetCount   = queryOne("SELECT COUNT(*) as c FROM assets")['c'] ?? 0;
$userCount    = queryOne("SELECT COUNT(*) as c FROM users WHERE active=1")['c'] ?? 0;
$installAccessible = @file_get_contents('http://localhost' . BASE_URL . '/install/index.php') !== false;

$totalPages = (int)ceil($totalRows / $perPage);
$pageTitle  = 'Systeem & Audit log';
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header">
    <h1>Systeem & Audit log</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<!-- Systeeminfo -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px;margin-bottom:20px;">
    <?php $stats = [
        ['PHP versie', $phpVersion],
        ['MySQL versie', $mysqlVersion],
        ['Totaal assets', $assetCount],
        ['Actieve gebruikers', $userCount],
    ]; ?>
    <?php foreach ($stats as [$lbl, $val]): ?>
    <div class="card" style="margin:0;">
        <div class="card-body" style="padding:15px;">
            <div style="font-size:0.8rem;color:#6b7280;text-transform:uppercase;"><?= $lbl ?></div>
            <div style="font-size:1.4rem;font-weight:bold;color:#1a2332;"><?= htmlspecialchars($val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (is_dir(__DIR__ . '/../../install')): ?>
<div class="alert alert-danger">
    ⚠️ <strong>Let op:</strong> De <code>install/</code> map is nog aanwezig op de server.
    Verwijder of hernoem deze map om veiligheidsredenen.
</div>
<?php endif; ?>

<!-- Audit log -->
<div class="card">
    <div class="card-body">
        <h3 style="margin-top:0;">Audit log</h3>
        <form method="GET" style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">
            <select name="user" class="form-control" style="width:180px;">
                <option value="">Alle gebruikers</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" class="form-control" style="width:160px;" value="<?= htmlspecialchars($filterDate) ?>">
            <button type="submit" class="btn btn-secondary">Filteren</button>
            <a href="<?= BASE_URL ?>/modules/settings/system.php" class="btn btn-secondary">Reset</a>
        </form>

        <table class="data-table">
            <thead>
                <tr><th>Datum/tijd</th><th>Gebruiker</th><th>Actie</th><th>Tabel</th><th>Record</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;">Geen log-regels gevonden.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= date('d-m-Y H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><?= htmlspecialchars($log['username'] ?? 'systeem') ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['table_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['record_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginering -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:8px;justify-content:center;margin-top:15px;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?><?= $filterUser ? '&user='.$filterUser : '' ?><?= $filterDate ? '&date='.urlencode($filterDate) : '' ?>"
               class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <p style="color:#6b7280;font-size:0.85rem;margin-top:10px;">Totaal <?= $totalRows ?> records</p>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
