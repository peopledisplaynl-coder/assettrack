<?php
/**
 * Location switcher for AssetTrack
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    verifyCsrfToken($_POST['csrf_token'] ?? '')) {

    $locationId = (int)($_POST['location_id'] ?? 0);

    if ($locationId) {
        // Valideer dat gebruiker toegang heeft tot deze locatie
        $locations = getUserLocations();
        foreach ($locations as $loc) {
            if ((int)$loc['id'] === $locationId) {
                setLocationId($locationId);
                break;
            }
        }
    }
}

header('Location: ' . BASE_URL . '/dashboard.php');
exit;
