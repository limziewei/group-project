<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function get_auth_accounts(): array
{
    return [
        'admin' => [
            'password' => 'admin123',
            'role' => 'admin',
            'name' => 'Admin',
            'display_name' => 'Admin User',
        ],
        'counter' => [
            'password' => 'counter123',
            'role' => 'counter',
            'name' => 'Counter Staff',
            'display_name' => 'Counter Staff',
        ],
    ];
}

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function login_user(array $user): void
{
    $_SESSION['auth_user'] = $user;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function require_role(array $allowed_roles): void
{
    $user = current_user();

    if (!$user) {
        header('Location: login.php');
        exit();
    }

    if ($allowed_roles && !in_array($user['role'], $allowed_roles, true)) {
        header('Location: login.php?error=unauthorized');
        exit();
    }
}