<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/fetcher.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/processor.php';

$isCli = PHP_SAPI === 'cli';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!$isCli) {
    require_once __DIR__ . '/../includes/auth.php';
    requireLogin();

    $role = $_SESSION['role']
        ?? $_SESSION['user_role']
        ?? ($_SESSION['user']['role'] ?? null);
    if ($role !== null && strtolower((string)$role) !== 'admin') {
        http_response_code(403);
        exit('Administrator access is required to run the RSS scheduler.');
    }
}

$sourceId = $isCli
    ? (isset($argv[1]) && filter_var($argv[1], FILTER_VALIDATE_INT) ? (int)$argv[1] : null)
    : (filter_input(INPUT_GET, 'source_id', FILTER_VALIDATE_INT) ?: null);

$sql = "
    SELECT id, name, source_type, url, category, status
    FROM sources
    WHERE status = 'active'
      AND source_type = 'rss'
";

$params = [];

if ($sourceId) {
    $sql .= " AND id = :source_id";
    $params['source_id'] = $sourceId;
}

$sql .= " ORDER BY id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sources = $stmt->fetchAll();

$fetcher = new AlertWatchRssFetcher(20);
$parser = new AlertWatchRssParser();
$processor = new AlertWatchRssProcessor($pdo);

$results = [];

foreach ($sources as $source) {
    $started = microtime(true);

    try {
        $response = $fetcher->fetch($source['url']);

        $records = $parser->parse(
            $response['body'],
            (string)($source['category'] ?? '')
        );

        $stats = $processor->process((int)$source['id'], $records);

        $update = $pdo->prepare("
            UPDATE sources
            SET last_fetched = NOW(), updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute(['id' => $source['id']]);

        $results[] = [
            'id' => (int)$source['id'],
            'name' => $source['name'],
            'success' => true,
            'fetched' => count($records),
            'inserted' => $stats['inserted'],
            'duplicates' => $stats['duplicates'],
            'failed' => $stats['failed'],
            'seconds' => round(microtime(true) - $started, 2),
        ];
    } catch (Throwable $e) {
        $results[] = [
            'id' => (int)$source['id'],
            'name' => $source['name'],
            'success' => false,
            'error' => $e->getMessage(),
            'seconds' => round(microtime(true) - $started, 2),
        ];
    }
}

if ($isCli) {
    echo "AlertWatch RSS Scheduler\n";
    echo "========================\n";

    if (!$sources) {
        echo "No active RSS sources found.\n";
        exit;
    }

    foreach ($results as $result) {
        if ($result['success']) {
            echo sprintf(
                "[OK] #%d %s | fetched=%d inserted=%d duplicates=%d failed=%d time=%ss\n",
                $result['id'],
                $result['name'],
                $result['fetched'],
                $result['inserted'],
                $result['duplicates'],
                $result['failed'],
                $result['seconds']
            );
        } else {
            echo sprintf(
                "[ERROR] #%d %s | %s\n",
                $result['id'],
                $result['name'],
                $result['error']
            );
        }
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RSS Scheduler - AlertWatch</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.topbar{background:#1f2d3d;color:#fff;padding:18px 25px;display:flex;justify-content:space-between}
.brand{font-size:22px;font-weight:700}.nav a{color:#fff;text-decoration:none;margin-left:22px}
.container{max-width:1100px;margin:35px auto;padding:0 20px}
.card{background:#fff;padding:25px;border-radius:9px;box-shadow:0 2px 10px rgba(0,0,0,.07)}
table{width:100%;border-collapse:collapse;margin-top:20px}th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc}.ok{color:#166534;font-weight:700}.error{color:#991b1b;font-weight:700}
.btn{display:inline-block;padding:10px 15px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px}
</style>
</head>
<body>
<header class="topbar">
<div class="brand">AlertWatch</div>
<nav class="nav">
<a href="<?= e(BASE_URL) ?>/">Dashboard</a>
<a href="<?= e(BASE_URL) ?>/alerts/index.php">Alerts</a>
<a href="<?= e(BASE_URL) ?>/sources/index.php">Sources</a>
<a href="<?= e(BASE_URL) ?>/logout.php">Logout</a>
</nav>
</header>

<main class="container">
<div class="card">
<h1>RSS Scheduler</h1>
<p>RSS processing completed for <?= count($results) ?> source(s).</p>
<a class="btn" href="scheduler.php">Run Again</a>

<?php if (!$results): ?>
<p>No active RSS sources were found.</p>
<?php else: ?>
<table>
<thead>
<tr><th>Source</th><th>Result</th><th>Fetched</th><th>New Alerts</th><th>Duplicates</th><th>Failed</th><th>Time</th></tr>
</thead>
<tbody>
<?php foreach ($results as $result): ?>
<tr>
<td>#<?= (int)$result['id'] ?> - <?= e($result['name']) ?></td>
<?php if ($result['success']): ?>
<td class="ok">Success</td>
<td><?= (int)$result['fetched'] ?></td>
<td><?= (int)$result['inserted'] ?></td>
<td><?= (int)$result['duplicates'] ?></td>
<td><?= (int)$result['failed'] ?></td>
<td><?= e($result['seconds']) ?> sec</td>
<?php else: ?>
<td class="error">Error: <?= e($result['error']) ?></td>
<td colspan="5">See error message</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</main>
</body>
</html>
