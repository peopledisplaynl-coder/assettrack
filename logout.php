<?php
/**
 * Logout page for AssetTrack
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;
