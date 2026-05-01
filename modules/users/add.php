<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_users');

$errors = [];
$data   = ['full_name' => '', 'username' => '', 'email' => '', 'role' => 'user', 'active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token. Probeer opnieuw.';
    } else {
        $data['full_name'] = trim($_POST['full_name'] ?? '');
        $data['username']  = trim($_POST['username'] ?? '');
        $data['email']     = trim($_POST['email'] ?? '');
        $data['role']      = $_POST['role'] ?? 'user';
        $data['active']    = isset($_POST['active']) ? 1 : 0;
        $password          = $_POST['password'] ?? '';
        $password2         = $_POST['password2'] ?? '';

        if (!$data['full_name']) $errors[] = 'Volledige naam is verplicht.';
        if (!$data['username'])  $errors[] = 'Gebruikersnaam is verplicht.';
        if (!$data['email'] || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geldig e-mailadres is verplicht.';
        if (!$password)          $errors[] = 'Wachtwoord is verplicht.';
        if (strlen($password) < 8) $errors[] = 'Wachtwoord moet minimaal 8 tekens zijn.';
        if ($password !== $password2) $errors[] = 'Wachtwoorden komen niet overeen.';

        // Admin mag geen superadmin aanmaken
        if (getRole() === 'admin' && $data['role'] === 'superadmin') {
            $errors[] = 'U mag geen superadmin aanmaken.';
        }

        $allowedRoles = ['superadmin', 'admin', 'user', 'visitor'];
        if (!in_array($data['role'], $allowedRoles)) $errors[] = 'Ongeldige rol.';

        if (empty($errors)) {
            // Check uniek
            $existing = queryOne("SELECT id FROM users WHERE username = ? OR email = ?", [$data['username'], $data['email']]);
            if ($existing) {
                $errors[] = 'Gebruikersnaam of e-mailadres is al in gebruik.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                execute("INSERT INTO users (full_name, username, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$data['full_name'], $data['username'], $data['email'], $hash, $data['role'], $data['active']]);

                $userId = queryOne("SELECT LAST_INSERT_ID() as id")['id'];

                // Voeg locatie rechten toe
                $userLocations = getUserLocations(); // Locaties waar de huidige gebruiker toegang toe heeft
                foreach ($userLocations as $location) {
                    $canView = isset($_POST['location_' . $location['id'] . '_view']) ? 1 : 0;
                    $canEdit = isset($_POST['location_' . $location['id'] . '_edit']) ? 1 : 0;

                    if ($canView || $canEdit) {
                        execute("INSERT INTO user_locations (user_id, location_id, can_view, can_edit) VALUES (?, ?, ?, ?)",
                            [$userId, $location['id'], $canView, $canEdit]);
                    }
                }

                header('Location: ' . BASE_URL . '/modules/users/?success=Gebruiker+succesvol+aangemaakt');
                exit;
            }
        }
    }
}

$pageTitle = 'Gebruiker toevoegen';
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header">
    <h1>Nieuwe gebruiker</h1>
    <a href="<?= BASE_URL ?>/modules/users/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="form-group">
                <label>Volledige naam *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($data['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Gebruikersnaam *</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($data['username']) ?>" required>
            </div>
            <div class="form-group">
                <label>E-mailadres *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>" required>
            </div>
            <div class="form-group">
                <label>Wachtwoord * (minimaal 8 tekens)</label>
                <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <div class="form-group">
                <label>Wachtwoord bevestigen *</label>
                <input type="password" name="password2" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Rol *</label>
                <select name="role" class="form-control" required>
                    <?php if (getRole() === 'superadmin'): ?>
                    <option value="superadmin" <?= $data['role'] === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                    <?php endif; ?>
                    <option value="admin"   <?= $data['role'] === 'admin'   ? 'selected' : '' ?>>Admin</option>
                    <option value="user"    <?= $data['role'] === 'user'    ? 'selected' : '' ?>>User</option>
                    <option value="visitor" <?= $data['role'] === 'visitor' ? 'selected' : '' ?>>Visitor</option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="active" value="1" <?= $data['active'] ? 'checked' : '' ?>>
                    Actief
                </label>
            </div>

            <div class="form-group">
                <label><strong>Locatie rechten:</strong></label>
                <div style="margin-top:10px;">
                    <?php
                    $userLocations = getUserLocations();
                    foreach ($userLocations as $location):
                    ?>
                    <div style="margin-bottom:10px;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <strong><?= htmlspecialchars($location['org_name'] . ' - ' . $location['name']) ?></strong><br>
                        <label style="margin-right:20px;">
                            <input type="checkbox" name="location_<?= $location['id'] ?>_view" value="1">
                            Kan bekijken
                        </label>
                        <label>
                            <input type="checkbox" name="location_<?= $location['id'] ?>_edit" value="1">
                            Kan bewerken
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">Gebruiker aanmaken</button>
                <a href="<?= BASE_URL ?>/modules/users/" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
