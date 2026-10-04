<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$userId = currentUserId();

$stmt = $pdo->prepare("
    SELECT id, category, location, severity, notification_method, status, created_at, updated_at
    FROM subscriptions
    WHERE user_id = :user_id
      AND status = 'active'
    ORDER BY id DESC
");
$stmt->execute(['user_id' => $userId]);
$subscriptions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Subscriptions - AlertWatch</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.topbar{background:#1f2d3d;color:#fff;padding:18px 25px;display:flex;justify-content:space-between;align-items:center}
.brand{font-size:22px;font-weight:700}.nav a{color:#fff;text-decoration:none;margin-left:22px}
.container{max-width:1100px;margin:35px auto;padding:0 20px}.heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.add{background:#2563eb;color:#fff;text-decoration:none;padding:10px 15px;border-radius:6px}
.card{background:#fff;padding:20px;border-radius:9px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow-x:auto}
table{width:100%;border-collapse:collapse}th,td{padding:13px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize}
.dashboard{background:#e0e7ff;color:#3730a3}.email{background:#dbeafe;color:#1e40af}
button{padding:8px 12px;border:0;border-radius:5px;background:#dc2626;color:#fff;cursor:pointer}
.empty{text-align:center;padding:40px;color:#6b7280}
</style>
</head>
<body>
<header class="topbar">
<div class="brand">AlertWatch</div>
<nav class="nav">
<span><?= e($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'User') ?></span>
<a href="<?= e(BASE_URL) ?>/">Dashboard</a>
<a href="<?= e(BASE_URL) ?>/alerts/index.php">Alerts</a>
<a href="<?= e(BASE_URL) ?>/logout.php">Logout</a>
</nav>
</header>
<main class="container">
<div class="heading">
<h1>My Subscriptions</h1>
<a class="add" href="preferences.php">Manage Preferences</a>
</div>
<div class="card">
<?php if (!$subscriptions): ?>
<div class="empty">No active subscriptions found.<br><br><a href="preferences.php">Create your first subscription</a></div>
<?php else: ?>
<table>
<thead><tr><th>ID</th><th>Category</th><th>Location</th><th>Severity</th><th>Notification</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($subscriptions as $subscription): ?>
<tr>
<td><?= e($subscription['id']) ?></td>
<td><?= e($subscription['category'] ?: 'All') ?></td>
<td><?= e($subscription['location'] ?: 'All') ?></td>
<td><?= e($subscription['severity'] ?: 'Any') ?></td>
<td><span class="badge <?= $subscription['notification_method'] === 'email' ? 'email' : 'dashboard' ?>"><?= e(ucfirst($subscription['notification_method'])) ?></span></td>
<td><?= e(ucfirst($subscription['status'])) ?></td>
<td><?= e(date('d M Y, h:i A', strtotime($subscription['created_at']))) ?></td>
<td>
<form method="post" action="unsubscribe.php" onsubmit="return confirm('Unsubscribe from this alert subscription?');">
<input type="hidden" name="id" value="<?= (int)$subscription['id'] ?>">
<button type="submit">Unsubscribe</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
