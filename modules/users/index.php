<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_users');

$success    = $_GET['success'] ?? '';
$error      = $_GET['error'] ?? '';
$search     = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$sql    = "SELECT * FROM users WHERE 1=1";
$params = [];

if (getRole() === 'admin') {
    $sql .= " AND role != 'superadmin'";
}
if ($roleFilter) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($search) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY role, username";

try {
    $users = query($sql, $params);
} catch (Exception $e) {
    $users = [];
    $error = "Fout bij laden gebruikers: " . $e->getMessage();
}

$pageTitle = 'Gebruikers';
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header">
    <h1>Gebruikers</h1>
    <a href="<?= BASE_URL ?>/modules/users/add.php" class="btn btn-primary">+ Nieuwe gebruiker</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Zoek op naam of e-mail..."
                   value="<?= htmlspecialchars($search) ?>" class="form-control" style="flex:1;min-width:200px;">
            <select name="role" class="form-control" style="width:180px;">
                <option value="">Alle rollen</option>
                <?php if (getRole() === 'superadmin'): ?>
                <option value="superadmin" <?= $roleFilter === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                <?php endif; ?>
                <option value="admin"   <?= $roleFilter === 'admin'   ? 'selected' : '' ?>>Admin</option>
                <option value="user"    <?= $roleFilter === 'user'    ? 'selected' : '' ?>>User</option>
                <option value="visitor" <?= $roleFilter === 'visitor' ? 'selected' : '' ?>>Visitor</option>
            </select>
            <button type="submit" class="btn btn-secondary">Zoeken</button>
            <a href="<?= BASE_URL ?>/modules/users/" class="btn btn-secondary">Reset</a>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Gebruikersnaam</th>
                    <th>E-mail</th>
                    <th>Rol</th>
                    <th>Status</th>
                    <th>Laatste login</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center;padding:20px;">Geen gebruikers gevonden.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="badge badge-<?= $user['role'] === 'superadmin' ? 'danger' : ($user['role'] === 'admin' ? 'warning' : 'info') ?>"><?= $user['role'] ?></span></td>
                    <td><span class="badge <?= $user['active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $user['active'] ? 'Actief' : 'Inactief' ?></span></td>
                    <td><?= $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : 'Nooit' ?></td>
                    <td class="actions">
                        <a href="<?= BASE_URL ?>/modules/users/edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-secondary">Bewerken</a>
                        <?php if ($user['id'] != getUserId()): ?>
                        <a href="<?= BASE_URL ?>/modules/users/toggle.php?id=<?= $user['id'] ?>&csrf=<?= generateCsrfToken() ?>"
                           class="btn btn-sm <?= $user['active'] ? 'btn-warning' : 'btn-success' ?>"
                           onclick="return confirm('Weet u zeker dat u deze gebruiker wilt <?= $user['active'] ? 'deactiveren' : 'activeren' ?>?')">
                            <?= $user['active'] ? 'Deactiveren' : 'Activeren' ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if (getRole() === 'superadmin'): ?>
        <div style="margin-top:20px;padding-top:15px;border-top:1px solid #e5e7eb;">
            <a href="<?= BASE_URL ?>/modules/users/permissions.php" class="btn btn-secondary">⚙️ Rechtenbeheer</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
