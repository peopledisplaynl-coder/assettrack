<?php
/**
 * Authentication and session management
 * AssetTrack - IT Asset Management System
 */

// Sessie-timeout na inactiviteit, in seconden. Stond op 1800 (30 minuten) --
// op verzoek van Ton verlengd naar 28800 (8 uur, een hele school-/werkdag)
// zodat je tussendoor niet steeds opnieuw hoeft in te loggen.
if (!defined('SESSION_TIMEOUT_SECONDS')) {
    define('SESSION_TIMEOUT_SECONDS', 28800);
}

// "Onthoud mij" -- optioneel vinkje bij inloggen waarmee een gebruiker tot 30
// dagen ingelogd blijft, los van de gewone sessie-timeout hierboven. Dit werkt
// via een apart, langlevend cookie + een bijbehorend token in de database (zie
// tabel remember_tokens) -- dus niet via PHP's eigen sessiemechanisme, en blijft
// daarom ook werken als de sessie zelf verloopt of door de server wordt
// opgeruimd (zie ook de kanttekening over sessie-opschoning door de hosting-
// provider, waar dit een praktische oplossing voor is).
if (!defined('REMEMBER_ME_COOKIE_NAME')) {
    define('REMEMBER_ME_COOKIE_NAME', 'assettrack_remember');
}
if (!defined('REMEMBER_ME_LIFETIME_SECONDS')) {
    define('REMEMBER_ME_LIFETIME_SECONDS', 30 * 86400); // 30 dagen
}

// Sessie instellingen VOOR session_start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT_SECONDS);
    session_start();
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// Nog niet (meer) ingelogd via de sessie? Probeer dan het "onthoud mij"-cookie,
// indien aanwezig en geldig -- zie tryRememberMeLogin() verderop in dit bestand.
if (!isLoggedIn()) {
    tryRememberMeLogin();
}

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

function login(string $usernameOrEmail, string $password, bool $rememberMe = false): bool {
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
            if ($rememberMe) {
                issueRememberMeToken((int)$user['id']);
            }
            return true;
        }
    } catch (Exception $e) {
        error_log("Login fout: " . $e->getMessage());
    }
    return false;
}

function logout(): void {
    clearRememberMeToken();
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// ─── "Onthoud mij" (30 dagen ingelogd blijven) ─────────────────────────────────
// Selector/validator-opzet (de gangbare, veilige aanpak voor langlevende login-
// cookies): de selector is de publieke zoeksleutel die in de tabel wordt
// opgezocht, de validator wordt nooit in platte tekst opgeslagen (alleen een
// sha256-hash ervan) en bij elk gebruik direct vervangen door een nieuw token.
// Zo is het opgeslagen cookie op zichzelf niet genoeg om iemands account over
// te nemen als de database ooit zou uitlekken, en wordt eenzelfde cookiewaarde
// nooit twee keer gebruikt.

function rememberMeCookieOptions(int $expiresAt): array {
    return [
        'expires'  => $expiresAt,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function issueRememberMeToken(int $userId): void {
    try {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = time() + REMEMBER_ME_LIFETIME_SECONDS;
        execute(
            "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)",
            [$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expiresAt)]
        );
        setcookie(REMEMBER_ME_COOKIE_NAME, $selector . ':' . $validator, rememberMeCookieOptions($expiresAt));
    } catch (Exception $e) {
        error_log("Onthoud-mij token aanmaken mislukt: " . $e->getMessage());
    }
}

function clearRememberMeToken(): void {
    if (!empty($_COOKIE[REMEMBER_ME_COOKIE_NAME])) {
        $parts = explode(':', $_COOKIE[REMEMBER_ME_COOKIE_NAME], 2);
        if (!empty($parts[0])) {
            try {
                execute("DELETE FROM remember_tokens WHERE selector = ?", [$parts[0]]);
            } catch (Exception $e) {
                // Negeren -- het cookie wordt hieronder sowieso verwijderd.
            }
        }
    }
    setcookie(REMEMBER_ME_COOKIE_NAME, '', rememberMeCookieOptions(time() - 3600));
    unset($_COOKIE[REMEMBER_ME_COOKIE_NAME]);
}

function tryRememberMeLogin(): void {
    if (empty($_COOKIE[REMEMBER_ME_COOKIE_NAME])) return;

    $parts = explode(':', $_COOKIE[REMEMBER_ME_COOKIE_NAME], 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        clearRememberMeToken();
        return;
    }
    [$selector, $validator] = $parts;

    try {
        $token = queryOne("SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()", [$selector]);
    } catch (Exception $e) {
        return; // bijv. tabel bestaat nog niet (migratie nog niet uitgevoerd) -- stil negeren
    }

    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        // Ongeldig, verlopen, of een hergebruikt (dus mogelijk gestolen) token --
        // in alle gevallen het cookie opruimen zodat er niet steeds opnieuw een
        // vergeefse database-lookup gebeurt.
        clearRememberMeToken();
        return;
    }

    $user = queryOne("SELECT * FROM users WHERE id = ? AND active = 1", [$token['user_id']]);
    if (!$user) {
        execute("DELETE FROM remember_tokens WHERE id = ?", [$token['id']]);
        clearRememberMeToken();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();
    execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

    // Rotatie: dit ene token direct vervangen door een nieuw token/cookie, zodat
    // eenzelfde cookiewaarde niet blijvend herbruikbaar is.
    execute("DELETE FROM remember_tokens WHERE id = ?", [$token['id']]);
    issueRememberMeToken((int)$user['id']);
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