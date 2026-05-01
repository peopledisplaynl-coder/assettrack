<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePermission('manage_users');

$id   = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!verifyCsrfToken($csrf)) {
    header('Location: ' . BASE_URL . '/modules/users/?error=Ongeldige+aanvraag');
    exit;
}
if ($id === getUserId()) {
    header('Location: ' . BASE_URL . '/modules/users/?error=U+kunt+uzelf+niet+deactiveren');
    exit;
}
$user = queryOne("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user || (getRole() === 'admin' && $user['role'] === 'superadmin')) {
    header('Location: ' . BASE_URL . '/modules/users/?error=Geen+toegang');
    exit;
}
$newStatus = $user['active'] ? 0 : 1;
execute("UPDATE users SET active = ? WHERE id = ?", [$newStatus, $id]);
$msg = $newStatus ? 'Gebruiker+geactiveerd' : 'Gebruiker+gedeactiveerd';
header('Location: ' . BASE_URL . '/modules/users/?success=' . $msg);
exit;
