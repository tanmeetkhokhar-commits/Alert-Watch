<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?error=' . urlencode('Invalid alert ID.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        a.id, a.source_id, a.external_id, a.title, a.description,
        a.category, a.location, a.severity, a.published_at,
        a.source_url, a.status, a.created_at, a.updated_at,
        s.name AS source_name,
        s.source_type
    FROM alerts a
    LEFT JOIN sources s ON s.id = a.source_id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$alert = $stmt->fetch();

if (!$alert) {
    header('Location: index.php?error=' . urlencode('Alert not found.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($alert['title']) ?> - AlertWatch</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; color:#1f2937; }
        .topbar { background:#1f2d3d; color:#fff; padding:18px 25px; display:flex; justify-content:space-between; }
        .brand { font-size:22px; font-weight:700; }
        .nav a { color:#fff; text-decoration:none; margin-left:22px; }
        .container { max-width:1000px; margin:35px auto; padding:0 20px; }
        .card { background:#fff; padding:28px; border-radius:9px; box-shadow:0 2px 10px rgba(0,0,0,.07); }
        h1 { margin-top:0; line-height:1.3; }
        .meta { display:grid; grid-template-columns:repeat(2,1fr); gap:15px; margin:25px 0; }
        .meta-item { background:#f8fafc; padding:14px; border-radius:7px; }
        .label { display:block; color:#6b7280; font-size:12px; margin-bottom:5px; text-transform:uppercase; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; text-transform:capitalize; }
        .low { background:#dcfce7; color:#166534; }
        .medium { background:#fef3c7; color:#92400e; }
        .high { background:#fed7aa; color:#9a3412; }
        .critical { background:#fee2e2; color:#991b1b; }
        .description { line-height:1.7; white-space:pre-wrap; margin-top:20px; }
        .source-link { word-break:break-all; }
        .back { display:inline-block; margin-top:20px; padding:10px 15px; background:#2563eb; color:#fff; border-radius:6px; text-decoration:none; }
        @media(max-width:700px) { .meta { grid-template-columns:1fr; } }
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
        <h1><?= e($alert['title']) ?></h1>

        <div class="meta">
            <div class="meta-item">
                <span class="label">Source</span>
                <?= e($alert['source_name'] ?? 'Unknown') ?>
            </div>
            <div class="meta-item">
                <span class="label">Source Type</span>
                <?= e(strtoupper($alert['source_type'] ?? '-')) ?>
            </div>
            <div class="meta-item">
                <span class="label">Category</span>
                <?= e($alert['category'] ?? '-') ?>
            </div>
            <div class="meta-item">
                <span class="label">Location</span>
                <?= e($alert['location'] ?? '-') ?>
            </div>
            <div class="meta-item">
                <span class="label">Severity</span>
                <span class="badge <?= e(strtolower($alert['severity'])) ?>">
                    <?= e($alert['severity']) ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="label">Status</span>
                <?= e($alert['status']) ?>
            </div>
            <div class="meta-item">
                <span class="label">Published</span>
                <?= $alert['published_at'] ? e(date('d M Y, h:i A', strtotime($alert['published_at']))) : '-' ?>
            </div>
            <div class="meta-item">
                <span class="label">Alert ID</span>
                <?= e($alert['id']) ?>
            </div>
        </div>

        <?php if (!empty($alert['description'])): ?>
            <h3>Description</h3>
            <div class="description"><?= e(strip_tags($alert['description'])) ?></div>
        <?php endif; ?>

        <?php if (!empty($alert['source_url'])): ?>
            <h3>Source URL</h3>
            <div class="source-link">
                <a href="<?= e($alert['source_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= e($alert['source_url']) ?>
                </a>
            </div>
        <?php endif; ?>

        <a class="back" href="index.php">← Back to Alerts</a>
    </div>
</main>
</body>
</html>
