<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$q = trim($_GET['q'] ?? '');
$alerts = [];

if ($q !== '') {
    $stmt = $pdo->prepare("
        SELECT
            a.id, a.title, a.description, a.category, a.location,
            a.severity, a.published_at, a.source_url, a.status,
            s.name AS source_name
        FROM alerts a
        LEFT JOIN sources s ON s.id = a.source_id
        WHERE
            a.title LIKE :q
            OR a.description LIKE :q
            OR a.category LIKE :q
            OR a.location LIKE :q
            OR s.name LIKE :q
        ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC
    ");
    $stmt->execute(['q' => '%' . $q . '%']);
    $alerts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Alerts - AlertWatch</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; color:#1f2937; }
        .topbar { background:#1f2d3d; color:#fff; padding:18px 25px; display:flex; justify-content:space-between; }
        .brand { font-size:22px; font-weight:700; }
        .nav a { color:#fff; text-decoration:none; margin-left:22px; }
        .container { max-width:1200px; margin:35px auto; padding:0 20px; }
        .card { background:#fff; padding:22px; border-radius:9px; box-shadow:0 2px 10px rgba(0,0,0,.07); margin-bottom:20px; }
        form { display:flex; gap:10px; }
        input[type=text] { flex:1; padding:12px; border:1px solid #d1d5db; border-radius:6px; font-size:15px; }
        button { padding:12px 20px; border:0; border-radius:6px; background:#2563eb; color:#fff; cursor:pointer; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:12px 10px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; }
        a { color:#1d4ed8; text-decoration:none; }
        .muted { color:#6b7280; font-size:13px; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; text-transform:capitalize; }
        .low { background:#dcfce7; color:#166534; }
        .medium { background:#fef3c7; color:#92400e; }
        .high { background:#fed7aa; color:#9a3412; }
        .critical { background:#fee2e2; color:#991b1b; }
        .empty { padding:25px; color:#6b7280; text-align:center; }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">AlertWatch</div>
    <nav class="nav">
        <span><?= e($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'User') ?></span>
        <a href="<?= e(BASE_URL) ?>/">Dashboard</a>
        <a href="index.php">Alerts</a>
        <a href="<?= e(BASE_URL) ?>/logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <div class="card">
        <h1>Search Alerts</h1>
        <form method="get" action="search.php">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search title, description, category, location or source">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($q !== ''): ?>
        <div class="card">
            <h2>Search results for "<?= e($q) ?>"</h2>

            <?php if (!$alerts): ?>
                <div class="empty">No alerts matched your search.</div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Alert</th>
                        <th>Source</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Severity</th>
                        <th>Published</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?= (int)$alert['id'] ?>">
                                    <?= e($alert['title']) ?>
                                </a>
                                <div class="muted">
                                    <?= e(mb_strimwidth(strip_tags($alert['description'] ?? ''), 0, 150, '...')) ?>
                                </div>
                            </td>
                            <td><?= e($alert['source_name'] ?? '-') ?></td>
                            <td><?= e($alert['category'] ?? '-') ?></td>
                            <td><?= e($alert['location'] ?? '-') ?></td>
                            <td><span class="badge <?= e(strtolower($alert['severity'])) ?>"><?= e($alert['severity']) ?></span></td>
                            <td><?= $alert['published_at'] ? e(date('d M Y, h:i A', strtotime($alert['published_at']))) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
