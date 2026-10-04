<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$sourceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($sourceId <= 0) {
    die('Invalid source ID.');
}

$stmt = $pdo->prepare("
    SELECT id, name, source_type, url, category, status
    FROM sources
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sourceId]);
$source = $stmt->fetch();

if (!$source) {
    die('Source not found.');
}

$error = '';

$name = $source['name'];
$sourceType = $source['source_type'];
$url = $source['url'];
$category = $source['category'] ?? '';
$status = $source['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sourceType = strtolower(trim($_POST['source_type'] ?? 'rss'));
    $url = trim($_POST['url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = strtolower(trim($_POST['status'] ?? 'active'));

    if ($name === '') {
        $error = 'Source name is required.';
    } elseif (!in_array($sourceType, ['rss', 'api'], true)) {
        $error = 'Invalid source type.';
    } elseif ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid source URL.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid source status.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE sources
                SET
                    name = :name,
                    source_type = :source_type,
                    url = :url,
                    category = :category,
                    status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ':name' => $name,
                ':source_type' => $sourceType,
                ':url' => $url,
                ':category' => ($category !== '' ? $category : null),
                ':status' => $status,
                ':id' => $sourceId
            ]);

            header('Location: ' . BASE_URL . '/sources/index.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Unable to update source. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Source - <?php echo e(APP_NAME); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }
        .navbar {
            height: 60px;
            background: #1f2937;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
        }
        .brand { font-size: 21px; font-weight: bold; }
        .navbar a { color: #fff; text-decoration: none; margin-left: 20px; }
        .container { max-width: 900px; margin: auto; padding: 30px; }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        h2 { margin-top: 0; }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .actions { display: flex; gap: 10px; margin-top: 25px; }
        .btn {
            display: inline-block;
            padding: 11px 17px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
        }
        .hint {
            color: #6b7280;
            font-size: 13px;
            margin-top: 6px;
        }
        @media (max-width: 700px) {
            .container { padding: 15px; }
            .navbar { padding: 0 15px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="brand">AlertWatch</div>
    <div>
        <span><?php echo e(currentUserName()); ?></span>
        <a href="<?php echo BASE_URL; ?>/index.php">Dashboard</a>
        <a href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
    </div>
</nav>

<main class="container">
    <div class="card">
        <h2>Edit Alert Source</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Source Name</label>
                <input type="text" id="name" name="name"
                       value="<?php echo e($name); ?>"
                       maxlength="150" required>
            </div>

            <div class="form-group">
                <label for="source_type">Source Type</label>
                <select id="source_type" name="source_type" required>
                    <option value="rss" <?php echo $sourceType === 'rss' ? 'selected' : ''; ?>>RSS</option>
                    <option value="api" <?php echo $sourceType === 'api' ? 'selected' : ''; ?>>API</option>
                </select>
            </div>

            <div class="form-group">
                <label for="url">Source URL</label>
                <input type="url" id="url" name="url"
                       value="<?php echo e($url); ?>"
                       placeholder="https://example.com/feed.xml"
                       required>
                <div class="hint">Enter the public RSS feed or API endpoint.</div>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category"
                       value="<?php echo e($category); ?>"
                       maxlength="100">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Update Source</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
