<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Alerts';

$sql = "
    SELECT
        a.id,
        a.source_id,
        a.external_id,
        a.title,
        a.description,
        a.category,
        a.location,
        a.severity,
        a.published_at,
        a.source_url,
        a.status,
        a.created_at,
        s.name AS source_name
    FROM alerts a
    LEFT JOIN sources s ON s.id = a.source_id
    ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC
";

$stmt = $pdo->query($sql);
$alerts = $stmt->fetchAll();

function severityClass($severity): string
{
    return match (strtolower((string)$severity)) {
        'critical' => 'severity-critical',
        'high' => 'severity-high',
        'medium' => 'severity-medium',
        default => 'severity-low',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - AlertWatch</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }
        .topbar {
            background: #1f2d3d;
            color: #fff;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand { font-size: 22px; font-weight: 700; }
        .nav a {
            color: #fff;
            text-decoration: none;
            margin-left: 22px;
        }
        .container {
            max-width: 1350px;
            margin: 35px auto;
            padding: 0 20px;
        }
        .heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        h1 { margin: 0; font-size: 28px; }
        .actions a {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            color: #fff;
            text-decoration: none;
            margin-left: 8px;
            background: #2563eb;
        }
        .card {
            background: #fff;
            border-radius: 9px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th { background: #f8fafc; }
        .title a { color: #1d4ed8; text-decoration: none; font-weight: 600; }
        .title a:hover { text-decoration: underline; }
        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .severity-low { background: #dcfce7; color: #166534; }
        .severity-medium { background: #fef3c7; color: #92400e; }
        .severity-high { background: #fed7aa; color: #9a3412; }
        .severity-critical { background: #fee2e2; color: #991b1b; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-archived { background: #e5e7eb; color: #374151; }
        .muted { color: #6b7280; font-size: 13px; }
        .empty {
            text-align: center;
            padding: 45px;
            color: #6b7280;
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">AlertWatch</div>
    <nav class="nav">
        <span><?= e($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'User') ?></span>
        <a href="<?= e(BASE_URL) ?>/">Dashboard</a>
        <a href="<?= e(BASE_URL) ?>/logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <div class="heading">
        <h1>Alerts</h1>
        <div class="actions">
            <a href="search.php">Search Alerts</a>
            <a href="filter.php">Filter Alerts</a>
        </div>
    </div>

    <div class="card">
        <?php if (!$alerts): ?>
            <div class="empty">
                No alerts found. Fetch an active source to import alerts.
            </div>
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
                        <td class="title">
                            <a href="view.php?id=<?= (int)$alert['id'] ?>">
                                <?= e($alert['title']) ?>
                            </a>
                            <?php if (!empty($alert['description'])): ?>
                                <div class="muted">
                                    <?= e(mb_strimwidth(strip_tags($alert['description']), 0, 120, '...')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($alert['source_name'] ?? 'Unknown') ?></td>
                        <td><?= e($alert['category'] ?? '-') ?></td>
                        <td><?= e($alert['location'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= e(severityClass($alert['severity'])) ?>">
                                <?= e($alert['severity']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $alert['published_at'] ? e(date('d M Y, h:i A', strtotime($alert['published_at']))) : '-' ?>
                        </td>
                        <td>
                            <span class="badge <?= $alert['status'] === 'active' ? 'status-active' : 'status-archived' ?>">
                                <?= e($alert['status']) ?>
                            </span>
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
