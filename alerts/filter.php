<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$severity = strtolower(trim($_GET['severity'] ?? ''));
$status = strtolower(trim($_GET['status'] ?? ''));
$category = trim($_GET['category'] ?? '');
$sourceId = filter_input(INPUT_GET, 'source_id', FILTER_VALIDATE_INT);
$location = trim($_GET['location'] ?? '');

$allowedSeverity = ['low', 'medium', 'high', 'critical'];
$allowedStatus = ['active', 'archived'];

if (!in_array($severity, $allowedSeverity, true)) {
    $severity = '';
}

if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$sources = $pdo->query("
    SELECT id, name
    FROM sources
    ORDER BY name ASC
")->fetchAll();

$categories = $pdo->query("
    SELECT DISTINCT category
    FROM alerts
    WHERE category IS NOT NULL AND category <> ''
    ORDER BY category ASC
")->fetchAll();

$where = [];
$params = [];

if ($severity !== '') {
    $where[] = 'a.severity = :severity';
    $params['severity'] = $severity;
}

if ($status !== '') {
    $where[] = 'a.status = :status';
    $params['status'] = $status;
}

if ($category !== '') {
    $where[] = 'a.category = :category';
    $params['category'] = $category;
}

if ($sourceId) {
    $where[] = 'a.source_id = :source_id';
    $params['source_id'] = $sourceId;
}

if ($location !== '') {
    $where[] = 'a.location LIKE :location';
    $params['location'] = '%' . $location . '%';
}

$sql = "
    SELECT
        a.id, a.title, a.category, a.location, a.severity,
        a.published_at, a.status, s.name AS source_name
    FROM alerts a
    LEFT JOIN sources s ON s.id = a.source_id
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alerts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filter Alerts - AlertWatch</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; color:#1f2937; }
        .topbar { background:#1f2d3d; color:#fff; padding:18px 25px; display:flex; justify-content:space-between; }
        .brand { font-size:22px; font-weight:700; }
        .nav a { color:#fff; text-decoration:none; margin-left:22px; }
        .container { max-width:1250px; margin:35px auto; padding:0 20px; }
        .card { background:#fff; padding:22px; border-radius:9px; box-shadow:0 2px 10px rgba(0,0,0,.07); margin-bottom:20px; }
        .filters { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; align-items:end; }
        label { display:block; font-size:13px; font-weight:700; margin-bottom:6px; }
        input,select { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; background:#fff; }
        .buttons { display:flex; gap:8px; }
        button,.reset { padding:10px 15px; border:0; border-radius:6px; color:#fff; text-decoration:none; cursor:pointer; background:#2563eb; }
        .reset { background:#6b7280; display:inline-block; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:12px 10px; border-bottom:1px solid #e5e7eb; text-align:left; }
        th { background:#f8fafc; }
        a { color:#1d4ed8; text-decoration:none; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; text-transform:capitalize; }
        .low { background:#dcfce7; color:#166534; }
        .medium { background:#fef3c7; color:#92400e; }
        .high { background:#fed7aa; color:#9a3412; }
        .critical { background:#fee2e2; color:#991b1b; }
        .empty { text-align:center; padding:30px; color:#6b7280; }
        @media(max-width:900px) { .filters { grid-template-columns:repeat(2,1fr); } }
        @media(max-width:600px) { .filters { grid-template-columns:1fr; } }
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
        <h1>Filter Alerts</h1>

        <form method="get" action="filter.php">
            <div class="filters">
                <div>
                    <label for="severity">Severity</label>
                    <select name="severity" id="severity">
                        <option value="">All Severities</option>
                        <?php foreach ($allowedSeverity as $item): ?>
                            <option value="<?= e($item) ?>" <?= $severity === $item ? 'selected' : '' ?>>
                                <?= e(ucfirst($item)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($allowedStatus as $item): ?>
                            <option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>>
                                <?= e(ucfirst($item)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $row): ?>
                            <option value="<?= e($row['category']) ?>" <?= $category === $row['category'] ? 'selected' : '' ?>>
                                <?= e($row['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="source_id">Source</label>
                    <select name="source_id" id="source_id">
                        <option value="">All Sources</option>
                        <?php foreach ($sources as $source): ?>
                            <option value="<?= (int)$source['id'] ?>" <?= $sourceId === (int)$source['id'] ? 'selected' : '' ?>>
                                <?= e($source['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?= e($location) ?>" placeholder="e.g. Lahore">
                </div>
            </div>

            <div class="buttons" style="margin-top:15px;">
                <button type="submit">Apply Filters</button>
                <a class="reset" href="filter.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Results (<?= count($alerts) ?>)</h2>

        <?php if (!$alerts): ?>
            <div class="empty">No alerts match the selected filters.</div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Alert</th>
                    <th>Source</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Severity</th>
                    <th>Published</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($alerts as $alert): ?>
                    <tr>
                        <td><?= e($alert['id']) ?></td>
                        <td>
                            <a href="view.php?id=<?= (int)$alert['id'] ?>">
                                <?= e($alert['title']) ?>
                            </a>
                        </td>
                        <td><?= e($alert['source_name'] ?? '-') ?></td>
                        <td><?= e($alert['category'] ?? '-') ?></td>
                        <td><?= e($alert['location'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= e(strtolower($alert['severity'])) ?>">
                                <?= e($alert['severity']) ?>
                            </span>
                        </td>
                        <td><?= $alert['published_at'] ? e(date('d M Y, h:i A', strtotime($alert['published_at']))) : '-' ?></td>
                        <td><?= e($alert['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
