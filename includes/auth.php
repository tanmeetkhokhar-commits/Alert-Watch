<?php



if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}


function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}


function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}


function isAdmin(): bool
{
    return isset($_SESSION['user_role'])
        && $_SESSION['user_role'] === 'admin';
}


function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied.');
    }
}


function loginUser(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}


function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}