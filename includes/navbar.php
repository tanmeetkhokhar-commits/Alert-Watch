<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/AlertWatch';

function navIsActive(string $section): bool
{
    global $currentPath, $baseUrl;

    $path = rtrim($currentPath, '/');

    switch ($section) {
        case 'dashboard':
            return $path === '' || $path === $baseUrl || $path === $baseUrl . '/index.php';

        case 'alerts':
            return str_starts_with($path, $baseUrl . '/alerts');

        case 'sources':
            return str_starts_with($path, $baseUrl . '/sources');

        case 'rss':
            return str_starts_with($path, $baseUrl . '/rss');

        case 'subscriptions':
            return str_starts_with($path, $baseUrl . '/subscriptions');

        case 'admin':
            return str_starts_with($path, $baseUrl . '/admin');

        default:
            return false;
    }
}
?>

<nav class="aw-navbar">
    <div class="aw-nav-inner">

        <a class="aw-brand" href="<?= e($baseUrl . '/index.php') ?>">
            AlertWatch
        </a>

        <div class="aw-nav-links">

            <a
                href="<?= e($baseUrl . '/index.php') ?>"
                class="<?= navIsActive('dashboard') ? 'active' : '' ?>"
            >
                Dashboard
            </a>

            <a
                href="<?= e($baseUrl . '/alerts/index.php') ?>"
                class="<?= navIsActive('alerts') ? 'active' : '' ?>"
            >
                Alerts
            </a>

            <a
                href="<?= e($baseUrl . '/sources/index.php') ?>"
                class="<?= navIsActive('sources') ? 'active' : '' ?>"
            >
                Sources
            </a>

            <a
                href="<?= e($baseUrl . '/rss/scheduler.php') ?>"
                class="<?= navIsActive('rss') ? 'active' : '' ?>"
            >
                RSS Scheduler
            </a>

            <a
                href="<?= e($baseUrl . '/subscriptions/index.php') ?>"
                class="<?= navIsActive('subscriptions') ? 'active' : '' ?>"
            >
                Subscriptions
            </a>

            <?php if (isAdmin()): ?>
                <a
                    href="<?= e($baseUrl . '/admin/index.php') ?>"
                    class="<?= navIsActive('admin') ? 'active' : '' ?>"
                >
                    Admin
                </a>
            <?php endif; ?>

        </div>

        <div class="aw-nav-user">
            <span class="aw-user-name">
                <?= e(currentUserName() ?: 'User') ?>
            </span>

            <a href="<?= e($baseUrl . '/logout.php') ?>">
                Logout
            </a>
        </div>

    </div>
</nav>
