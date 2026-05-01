<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('manage_users');

// Alleen superadmin mag rechten beheren
if (getRole() !== 'superadmin') {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#dc2626;">Geen toegang</h2>
        <p>Alleen superadmin kan rechtenbeheer aanpassen.</p>
        <a href="' . BASE_URL . '/dashboard.php">Terug</a>
    </div>');
}

$success = '';
$errors  = [];

// Alle beschikbare permissies met labels en groepen
$permissionGroups = [
    'Assets' => [
        'view_assets'   => ['label' => 'Assets bekijken',       'icon' => '👁️'],
        'add_assets'    => ['label' => 'Assets toevoegen',      'icon' => '➕'],
        'edit_assets'   => ['label' => 'Assets bewerken',       'icon' => '✏️'],
        'delete_assets' => ['label' => 'Assets verwijderen',    'icon' => '🗑️'],
        'import_assets' => ['label' => 'Assets importeren',     'icon' => '📤'],
        'scan_assets'   => ['label' => 'QR Scanner gebruiken',  'icon' => '📷'],
        'print_labels'  => ['label' => 'Labels afdrukken',      'icon' => '🏷️'],
    ],
    'Rapporten' => [
        'view_reports'  => ['label' => 'Rapporten bekijken',    'icon' => '📊'],
        'export_data'   => ['label' => 'Data exporteren',       'icon' => '📥'],
    ],
    'Kennisbank' => [
        'view_kb'       => ['label' => 'Kennisbank lezen',      'icon' => '📚'],
        'manage_kb'     => ['label' => 'Kennisbank beheren',    'icon' => '✍️'],
    ],
    'Beheer' => [
        'manage_users'      => ['label' => 'Gebruikers beheren',     'icon' => '👥'],
        'manage_settings'   => ['label' => 'Instellingen beheren',   'icon' => '⚙️'],
        'manage_appearance' => ['label' => 'Weergave & Thema',       'icon' => '🎨'],
    ],
];

$roles = ['admin', 'user', 'visitor'];
$roleLabels = [
    'admin'   => ['label' => 'Admin',    'color' => '#7c3aed', 'desc' => 'Beheerder met beperkte systeemtoegang'],
    'user'    => ['label' => 'Gebruiker','color' => '#2563eb', 'desc' => 'Standaard medewerker'],
    'visitor' => ['label' => 'Bezoeker', 'color' => '#64748b', 'desc' => 'Alleen lezen'],
];

// POST: sla permissies op
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    try {
        foreach ($roles as $role) {
            foreach (array_merge(...array_values($permissionGroups)) as $key => $info) {
                $enabled = isset($_POST['perm'][$role][$key]) ? 1 : 0;
                execute(
                    "INSERT INTO role_permissions (role, permission_key, enabled)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE enabled = ?",
                    [$role, $key, $enabled, $enabled]
                );
            }
        }

        // Zorg dat nieuwe permissies ook bestaan voor alle rollen
        $allKeys = array_keys(array_merge(...array_values($permissionGroups)));
        foreach (array_merge($roles, ['superadmin']) as $role) {
            foreach ($allKeys as $key) {
                $exists = queryOne("SELECT id FROM role_permissions WHERE role=? AND permission_key=?", [$role, $key]);
                if (!$exists) {
                    $default = ($role === 'superadmin') ? 1 : 0;
                    execute("INSERT IGNORE INTO role_permissions (role, permission_key, enabled) VALUES (?,?,?)",
                        [$role, $key, $default]);
                }
            }
        }

        $success = 'Rechten succesvol opgeslagen.';
    } catch (Exception $e) {
        $errors[] = 'Fout bij opslaan: ' . $e->getMessage();
    }
}

// Laad huidige permissies
$currentPerms = [];
$allPerms = query("SELECT role, permission_key, enabled FROM role_permissions");
foreach ($allPerms as $p) {
    $currentPerms[$p['role']][$p['permission_key']] = (int)$p['enabled'];
}

// Voeg ontbrekende permissies toe aan DB (automatisch bij eerste keer laden)
$allKeys = array_keys(array_merge(...array_values($permissionGroups)));
foreach ($roles as $role) {
    foreach ($allKeys as $key) {
        if (!isset($currentPerms[$role][$key])) {
            execute("INSERT IGNORE INTO role_permissions (role, permission_key, enabled) VALUES (?,?,0)", [$role, $key]);
            $currentPerms[$role][$key] = 0;
        }
    }
}
// Superadmin altijd alles
foreach ($allKeys as $key) {
    if (!isset($currentPerms['superadmin'][$key])) {
        execute("INSERT IGNORE INTO role_permissions (role, permission_key, enabled) VALUES ('superadmin',?,1)", [$key]);
        $currentPerms['superadmin'][$key] = 1;
    }
}

$pageTitle = 'Rechtenbeheer';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1>🔐 Rechtenbeheer</h1>
        <p style="color:#6b7280;margin-top:4px;">Stel in wat elke rol mag doen. Superadmin heeft altijd alle rechten.</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/users/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<!-- Rol uitleg -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:24px;">
    <!-- Superadmin -->
    <div class="card" style="margin:0;border-left:4px solid #10b981;">
        <div class="card-body" style="padding:14px 16px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:999px;font-size:0.75rem;font-weight:700;">Superadmin</span>
            </div>
            <div style="font-size:0.78rem;color:#6b7280;">Volledige toegang. Niet aanpasbaar.</div>
        </div>
    </div>
    <?php foreach ($roleLabels as $role => $info): ?>
    <div class="card" style="margin:0;border-left:4px solid <?= $info['color'] ?>;">
        <div class="card-body" style="padding:14px 16px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <span style="background:<?= $info['color'] ?>20;color:<?= $info['color'] ?>;padding:2px 8px;border-radius:999px;font-size:0.75rem;font-weight:700;"><?= $info['label'] ?></span>
            </div>
            <div style="font-size:0.78rem;color:#6b7280;"><?= $info['desc'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Permissie matrix -->
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <?php foreach ($permissionGroups as $groupName => $permissions): ?>
    <div class="card" style="margin-bottom:16px;">
        <div style="padding:14px 20px;background:#f8fafc;border-bottom:1px solid #e5e7eb;
                    display:flex;align-items:center;gap:8px;">
            <h3 style="margin:0;font-size:0.95rem;color:#1a2332;"><?= $groupName ?></h3>
            <span style="font-size:0.75rem;color:#94a3b8;"><?= count($permissions) ?> rechten</span>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:10px 20px;text-align:left;font-size:0.78rem;color:#64748b;
                                   font-weight:600;text-transform:uppercase;letter-spacing:0.05em;
                                   border-bottom:1px solid #e5e7eb;width:40%;">
                            Recht
                        </th>
                        <!-- Superadmin -->
                        <th style="padding:10px 16px;text-align:center;font-size:0.78rem;
                                   border-bottom:1px solid #e5e7eb;width:15%;">
                            <span style="background:#d1fae5;color:#065f46;padding:3px 10px;
                                         border-radius:999px;font-size:0.73rem;font-weight:700;">
                                Superadmin
                            </span>
                        </th>
                        <?php foreach ($roleLabels as $role => $info): ?>
                        <th style="padding:10px 16px;text-align:center;font-size:0.78rem;
                                   border-bottom:1px solid #e5e7eb;width:15%;">
                            <span style="background:<?= $info['color'] ?>20;color:<?= $info['color'] ?>;
                                         padding:3px 10px;border-radius:999px;font-size:0.73rem;font-weight:700;">
                                <?= $info['label'] ?>
                            </span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $key => $info): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <td style="padding:12px 20px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:1rem;"><?= $info['icon'] ?></span>
                                <div>
                                    <div style="font-weight:600;font-size:0.875rem;color:#1a2332;">
                                        <?= htmlspecialchars($info['label']) ?>
                                    </div>
                                    <div style="font-size:0.73rem;color:#94a3b8;font-family:monospace;">
                                        <?= $key ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <!-- Superadmin: altijd aan, niet aanpasbaar -->
                        <td style="padding:12px 16px;text-align:center;">
                            <span style="font-size:1.2rem;">✅</span>
                        </td>
                        <?php foreach ($roles as $role): ?>
                        <td style="padding:12px 16px;text-align:center;">
                            <label style="display:inline-flex;align-items:center;cursor:pointer;">
                                <input type="checkbox"
                                       name="perm[<?= $role ?>][<?= $key ?>]"
                                       value="1"
                                       <?= ($currentPerms[$role][$key] ?? 0) ? 'checked' : '' ?>
                                       style="width:18px;height:18px;cursor:pointer;
                                              accent-color:var(--color-primary,#2563eb);">
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:12px;align-items:center;margin-top:8px;">
        <button type="submit" class="btn btn-primary">💾 Rechten opslaan</button>
        <span style="font-size:0.82rem;color:#6b7280;">
            Wijzigingen gelden direct voor alle gebruikers met die rol.
        </span>
    </div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
