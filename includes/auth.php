<?php
/**
 * Authentication and session management
 * AssetTrack - IT Asset Management System
 */

// Sessie instellingen VOOR session_start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 1800);
    session_start();
}

// Session timeout check (30 minuten)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

function getUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function getUserName(): string {
    return $_SESSION['username'] ?? '';
}

function getRole(): string {
    return $_SESSION['role'] ?? 'visitor';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function hasPermission(string $permission): bool {
    if (!isLoggedIn()) return false;
    if (getRole() === 'superadmin') return true;
    try {
        $result = queryOne(
            "SELECT enabled FROM role_permissions WHERE role = ? AND permission_key = ?",
            [getRole(), $permission]
        );
        return $result && (int)$result['enabled'] === 1;
    } catch (Exception $e) {
        return false;
    }
}

function requirePermission(string $permission): void {
    requireLogin();
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
            <h2 style="color:#dc2626;">Geen toegang</h2>
            <p>U heeft niet de benodigde rechten voor deze pagina.</p>
            <a href="' . BASE_URL . '/dashboard.php">Terug naar dashboard</a>
        </div>');
    }
}

function login(string $usernameOrEmail, string $password): bool {
    try {
        $user = queryOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1",
            [$usernameOrEmail, $usernameOrEmail]
        );
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();
            execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            return true;
        }
    } catch (Exception $e) {
        error_log("Login fout: " . $e->getMessage());
    }
    return false;
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCurrentUsername(): string { return $_SESSION['username'] ?? ''; }
function getCurrentUserId(): int { return (int)($_SESSION['user_id'] ?? 0); }
function getCurrentUserRole(): string { return $_SESSION['role'] ?? 'visitor'; }

// Locatie functies voor multi-locatie ondersteuning
function getLocationId(): int {
    return (int)($_SESSION['location_id'] ?? 0);
}

function setLocationId(int $id): void {
    $_SESSION['location_id'] = $id;
}

function getUserLocations(): array {
    if (!isLoggedIn()) return [];
    if (getRole() === 'superadmin') {
        return query("SELECT l.*, o.name as org_name
                      FROM locations l
                      JOIN organisations o ON l.organisation_id = o.id
                      WHERE l.active = 1 ORDER BY o.name, l.name");
    }
    return query("SELECT l.*, o.name as org_name,
                         ul.can_view, ul.can_edit
                  FROM locations l
                  JOIN organisations o ON l.organisation_id = o.id
                  JOIN user_locations ul ON ul.location_id = l.id
                  WHERE ul.user_id = ? AND l.active = 1
                  ORDER BY o.name, l.name", [getUserId()]);
}

function canEditLocation(int $locationId = 0): bool {
    if (getRole() === 'superadmin') return true;
    if ($locationId === 0) $locationId = getLocationId();
    $result = queryOne(
        "SELECT can_edit FROM user_locations
         WHERE user_id = ? AND location_id = ?",
        [getUserId(), $locationId]
    );
    return $result && (int)$result['can_edit'] === 1;
}

function requireLocation(): void {
    if (getRole() !== 'superadmin' && !getLocationId()) {
        header('Location: ' . BASE_URL . '/select_location.php');
        exit;
    }
}