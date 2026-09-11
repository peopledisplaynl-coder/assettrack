<?php
/**
 * Mijn profiel — zelfbedieningspagina voor de ingelogde gebruiker om
 * profielfoto, e-mailadres en wachtwoord aan te passen. In tegenstelling tot
 * modules/users/edit.php (alleen voor beheerders, bewerkt ANDERE gebruikers)
 * heeft deze pagina geen speciale permissie nodig — elke ingelogde gebruiker
 * mag zijn eigen profiel bewerken.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$userId = getUserId();
$user   = queryOne("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$uploadDir = __DIR__ . '/../../assets/uploads/user_avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $action = $_POST['action'] ?? 'save_profile';

        if ($action === 'remove_photo') {
            if (!empty($user['avatar_filename'])) {
                $old = $uploadDir . $user['avatar_filename'];
                if (is_file($old)) {
                    @unlink($old);
                }
                execute("UPDATE users SET avatar_filename = NULL WHERE id = ?", [$userId]);
                $user['avatar_filename'] = null;
            }
            $success = 'Profielfoto verwijderd.';
        } else {
            $fullName  = trim($_POST['full_name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $currentPw = $_POST['current_password'] ?? '';
            $newPw     = $_POST['new_password'] ?? '';
            $newPw2    = $_POST['new_password2'] ?? '';

            $emailChanged    = $email !== $user['email'];
            $passwordChanged = $newPw !== '';

            if (!$fullName) {
                $errors[] = 'Volledige naam is verplicht.';
            }
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geldig e-mailadres is verplicht.';
            }
            if ($passwordChanged && strlen($newPw) < 8) {
                $errors[] = 'Nieuw wachtwoord moet minimaal 8 tekens zijn.';
            }
            if ($passwordChanged && $newPw !== $newPw2) {
                $errors[] = 'Nieuwe wachtwoorden komen niet overeen.';
            }

            // Huidig wachtwoord verplicht zodra e-mailadres of wachtwoord wijzigt
            // -- voorkomt dat iemand met een onbeheerde, ingelogde sessie (bv. een
            // gedeeld apparaat) zomaar het account kan overnemen.
            if (($emailChanged || $passwordChanged) && empty($errors)) {
                if (!$currentPw || !password_verify($currentPw, $user['password_hash'])) {
                    $errors[] = 'Huidig wachtwoord is onjuist. Dit is verplicht om je e-mailadres of wachtwoord te wijzigen.';
                }
            }

            if ($emailChanged && empty($errors)) {
                $existing = queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
                if ($existing) {
                    $errors[] = 'Dit e-mailadres is al in gebruik door een andere gebruiker.';
                }
            }

            // Profielfoto (optioneel, onafhankelijk van de rest van het formulier)
            $newAvatarFilename = $user['avatar_filename'];
            if (empty($errors) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $errors[] = 'Ongeldig bestandstype voor de profielfoto. Gebruik jpg, png, gif of webp.';
                } elseif ($_FILES['avatar']['size'] > 15 * 1024 * 1024) {
                    $errors[] = 'Maximale bestandsgrootte voor de profielfoto is 15MB.';
                } else {
                    // Vierkant, klein afdrukformaat (400px) is ruim genoeg voor een
                    // avatar -- geen reden om net als bij asset-foto's tot 1600px te gaan.
                    $filename    = 'avatar_' . $userId . '_' . time() . '.jpg';
                    $destination = $uploadDir . $filename;

                    if (!compressUploadedImage($_FILES['avatar']['tmp_name'], $destination, 400)) {
                        $errors[] = 'Kon de profielfoto niet verwerken. Probeer een andere foto.';
                    } else {
                        if (!empty($user['avatar_filename'])) {
                            $old = $uploadDir . $user['avatar_filename'];
                            if (is_file($old)) {
                                @unlink($old);
                            }
                        }
                        $newAvatarFilename = $filename;
                    }
                }
            }

            if (empty($errors)) {
                if ($passwordChanged) {
                    $hash = password_hash($newPw, PASSWORD_DEFAULT);
                    execute(
                        "UPDATE users SET full_name=?, email=?, password_hash=?, avatar_filename=? WHERE id=?",
                        [$fullName, $email, $hash, $newAvatarFilename, $userId]
                    );
                } else {
                    execute(
                        "UPDATE users SET full_name=?, email=?, avatar_filename=? WHERE id=?",
                        [$fullName, $email, $newAvatarFilename, $userId]
                    );
                }
                $_SESSION['email'] = $email;
                $user    = queryOne("SELECT * FROM users WHERE id = ?", [$userId]);
                $success = 'Profiel bijgewerkt.';
            }
        }
    }
}

$avatarUrl = getUserAvatarUrl($user['avatar_filename'] ?? null);
// Zelfde keuze als in templates/header.php: initiaal op basis van gebruikersnaam,
// niet volledige naam (die kan een organisatienaam bevatten).
$initial   = strtoupper(substr(trim($user['username'] ?: $user['full_name']), 0, 1)) ?: '?';

$pageTitle = 'Mijn profiel';
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header">
    <h1>Mijn profiel</h1>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Terug naar dashboard</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;flex-wrap:wrap;">
            <div class="profile-avatar-lg">
                <?php if ($avatarUrl): ?>
                <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="">
                <?php else: ?>
                <span class="profile-avatar-lg-initial"><?= htmlspecialchars($initial) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:1.05rem;"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
                <div style="color:#6b7280;font-size:0.85rem;">@<?= htmlspecialchars($user['username']) ?> — <?= htmlspecialchars(getRole()) ?></div>
                <?php if ($avatarUrl): ?>
                <form method="POST" style="margin-top:8px;display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="remove_photo">
                    <button type="submit" class="btn btn-sm btn-secondary">Profielfoto verwijderen</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="save_profile">

            <div class="form-group">
                <label>Profielfoto <?= $avatarUrl ? '(vervangen)' : '' ?></label>
                <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>

            <div class="form-group">
                <label>Volledige naam *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Gebruikersnaam</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                <small style="color:#6b7280;">Gebruikersnaam wijzigen? Vraag een beheerder.</small>
            </div>

            <div class="form-group">
                <label>E-mailadres *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <hr style="margin:20px 0;border:none;border-top:1px solid #e5e7eb;">

            <div class="form-group">
                <label>Nieuw wachtwoord (leeg laten = niet wijzigen)</label>
                <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Nieuw wachtwoord bevestigen</label>
                <input type="password" name="new_password2" class="form-control" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Huidig wachtwoord</label>
                <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                <small style="color:#6b7280;">Alleen verplicht als je hierboven je e-mailadres of wachtwoord wijzigt.</small>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">Opslaan</button>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
