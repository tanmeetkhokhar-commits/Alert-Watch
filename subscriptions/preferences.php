<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$userId = currentUserId();

$success = trim($_GET['success'] ?? '');
$error = trim($_GET['error'] ?? '');

$categories = $pdo->query("
    SELECT DISTINCT category
    FROM alerts
    WHERE category IS NOT NULL AND category <> ''
    ORDER BY category ASC
")->fetchAll();

$stmt = $pdo->prepare("
    SELECT id, category, location, severity, notification_method, status, created_at, updated_at
    FROM subscriptions
    WHERE user_id = :user_id
    ORDER BY id DESC
");
$stmt->execute(['user_id' => $userId]);
$subscriptions = $stmt->fetchAll();

$allowedSeverity = ['low', 'medium', 'high', 'critical'];
$allowedMethods = ['dashboard', 'email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscription Preferences - AlertWatch</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.topbar{background:#1f2d3d;color:#fff;padding:18px 25px;display:flex;justify-content:space-between;align-items:center}
.brand{font-size:22px;font-weight:700}.nav a{color:#fff;text-decoration:none;margin-left:22px}
.container{max-width:1150px;margin:35px auto;padding:0 20px}.card{background:#fff;padding:24px;border-radius:9px;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:20px}
h1,h2{margin-top:0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}input,select{width:100%;padding:11px;border:1px solid #d1d5db;border-radius:6px}
button{padding:11px 18px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer}
.alert{padding:12px 15px;border-radius:6px;margin-bottom:15px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}
table{width:100%;border-collapse:collapse}th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize}
.active{background:#dcfce7;color:#166534}.inactive{background:#e5e7eb;color:#374151}
.email{background:#dbeafe;color:#1e40af}.dashboard{background:#e0e7ff;color:#3730a3}
.delete{background:#dc2626}.empty{text-align:center;padding:30px;color:#6b7280}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.grid{grid-template-columns:1fr}}
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
<h1>Subscription Preferences</h1>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
<h2>Add Subscription</h2>
<form method="post" action="subscribe.php">
<div class="grid">
<div><label for="category">Category</label><select name="category" id="category">
<option value="">All Categories</option>
<?php foreach ($categories as $row): ?><option value="<?= e($row['category']) ?>"><?= e($row['category']) ?></option><?php endforeach; ?>
</select></div>
<div><label for="location">Location</label><input type="text" name="location" id="location" maxlength="255" placeholder="e.g. Lahore"></div>
<div><label for="severity">Minimum Severity</label><select name="severity" id="severity">
<option value="">Any Severity</option>
<?php foreach ($allowedSeverity as $item): ?><option value="<?= e($item) ?>"><?= e(ucfirst($item)) ?></option><?php endforeach; ?>
</select></div>
<div><label for="notification_method">Notification Method</label><select name="notification_method" id="notification_method">
<?php foreach ($allowedMethods as $method): ?><option value="<?= e($method) ?>"><?= e(ucfirst($method)) ?></option><?php endforeach; ?>
</select></div>
</div>
<div style="margin-top:18px"><button type="submit">Subscribe</button></div>
</form>
</div>

<div class="card">
<h2>My Subscriptions</h2>
<?php if (!$subscriptions): ?>
<div class="empty">No subscriptions found.</div>
<?php else: ?>
<table>
<thead><tr><th>ID</th><th>Category</th><th>Location</th><th>Severity</th><th>Notification</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($subscriptions as $subscription): ?>
<tr>
<td><?= e($subscription['id']) ?></td><td><?= e($subscription['category'] ?: 'All') ?></td><td><?= e($subscription['location'] ?: 'All') ?></td>
<td><?= e($subscription['severity'] ?: 'Any') ?></td>
<td><span class="badge <?= $subscription['notification_method'] === 'email' ? 'email' : 'dashboard' ?>"><?= e(ucfirst($subscription['notification_method'])) ?></span></td>
<td><span class="badge <?= $subscription['status'] === 'active' ? 'active' : 'inactive' ?>"><?= e(ucfirst($subscription['status'])) ?></span></td>
<td><?= e(date('d M Y, h:i A', strtotime($subscription['created_at']))) ?></td>
<td><?php if ($subscription['status'] === 'active'): ?><form method="post" action="unsubscribe.php" onsubmit="return confirm('Unsubscribe from this subscription?');"><input type="hidden" name="id" value="<?= (int)$subscription['id'] ?>"><button class="delete" type="submit">Unsubscribe</button></form><?php else: ?>-<?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
