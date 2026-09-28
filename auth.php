<?php

/*
 * Password disimpan sebagai hash, bukan plaintext.
 */

function appIsHttps()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function startSecureSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('absensi_auth');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => appIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

startSecureSession();

function adminUsername()
{
    return getenv('APP_ADMIN_USER') ?: 'admin';
}

function adminPasswordHash()
{
    $environmentHash = getenv('APP_ADMIN_PASSWORD_HASH');

    if ($environmentHash !== false && $environmentHash !== '') {
        return $environmentHash;
    }

    return '$2y$10$W4ejVbnHLBINkvTuANQUWeJ7ZxDAQ7i.1SyNT3riIu1hGbWUvkMMK';
}

function verifyAdminCredentials($username, $password)
{
    $usernameValid = hash_equals(
        adminUsername(),
        (string) $username
    );

    $passwordValid = password_verify(
        (string) $password,
        adminPasswordHash()
    );

    return $usernameValid && $passwordValid;
}

function isLoggedIn()
{
    if (($_SESSION['login'] ?? false) !== true) {
        return false;
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);

    if ($lastActivity > 0 && time() - $lastActivity > 7200) {
        unset(
            $_SESSION['login'],
            $_SESSION['username'],
            $_SESSION['login_time'],
            $_SESSION['last_activity']
        );

        session_regenerate_id(true);

        return false;
    }

    $_SESSION['last_activity'] = time();

    return true;
}

function loginAdmin($username)
{
    session_regenerate_id(true);

    $_SESSION['login'] = true;
    $_SESSION['username'] = (string) $username;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    unset(
        $_SESSION['login_attempts'],
        $_SESSION['login_locked_until']
    );
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfIsValid($token)
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function logoutAdmin()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax'
            ]
        );
    }

    session_destroy();
}
