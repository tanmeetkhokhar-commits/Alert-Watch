<?php
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/AlertWatch';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isAdminUser = function_exists('isAdmin') ? isAdmin() : (($_SESSION['user_role'] ?? '') === 'admin');
function awSidebarActive(string $path): string { global $currentPath; return str_contains($currentPath, $path) ? ' active' : ''; }
?>
<style>
.aw-sidebar{width:240px;flex:0 0 240px;background:#fff;border-right:1px solid #e5e7eb;padding:20px 14px;min-height:100%}.aw-sidebar-title{font-size:12px;text-transform:uppercase;color:#6b7280;font-weight:700;padding:8px 12px;margin-bottom:5px;letter-spacing:.05em}.aw-sidebar-link{display:block;padding:10px 12px;margin:3px 0;border-radius:7px;color:#374151;text-decoration:none;font-size:14px}.aw-sidebar-link:hover,.aw-sidebar-link.active{background:#eff6ff;color:#1d4ed8;text-decoration:none}@media(max-width:768px){.aw-sidebar{width:100%;flex-basis:auto;border-right:0;border-bottom:1px solid #e5e7eb}}
</style>
<aside class="aw-sidebar" aria-label="Sidebar navigation">
<div class="aw-sidebar-title">Navigation</div>
<a class="aw-sidebar-link<?= awSidebarActive('/index.php') ?>" href="<?= htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8') ?>/index.php">Dashboard</a>
<a class="aw-sidebar-link<?= awSidebarActive('/alerts/') ?>" href="<?= htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8') ?>/alerts/index.php">Alerts</a>
<a class="aw-sidebar-link<?= awSidebarActive('/sources/') ?>" href="<?= htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8') ?>/sources/index.php">Sources</a>
<a class="aw-sidebar-link<?= awSidebarActive('/rss/') ?>" href="<?= htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8') ?>/rss/scheduler.php">RSS Scheduler</a>
<a class="aw-sidebar-link<?= awSidebarActive('/subscriptions/') ?>" href="<?= htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8') ?>/subscriptions/index.php">Subscriptions</a>
<?php if($isAdminUser): ?><div class="aw-sidebar-title" style="margin-top:18px">Administration</div><a class="aw-sidebar-link<?= awSidebarActive('/admin/') ?>" href="<?= htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8') ?>/admin/index.php">Admin Panel</a><?php endif; ?>
</aside>
