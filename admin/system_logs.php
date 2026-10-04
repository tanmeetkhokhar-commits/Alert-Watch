<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'System Logs';

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$sourceFilter = filter_var(
    $_GET['source_id'] ?? '',
    FILTER_VALIDATE_INT
);

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "
        (
            l.action LIKE :search
            OR l.details LIKE :search
            OR a.title LIKE :search
            OR a.external_id LIKE :search
            OR s.name LIKE :search
        )
    ";

    $params['search'] = '%' . $search . '%';
}

if ($actionFilter !== '') {
    $where[] = "l.action = :action";
    $params['action'] = $actionFilter;
}

if ($sourceFilter) {
    $where[] = "a.source_id = :source_id";
    $params['source_id'] = $sourceFilter;
}

if ($dateFrom !== '') {
    $dateFromValid = DateTime::createFromFormat(
        'Y-m-d',
        $dateFrom
    );

    if ($dateFromValid && $dateFromValid->format('Y-m-d') === $dateFrom) {
        $where[] = "l.created_at >= :date_from";
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
}

if ($dateTo !== '') {
    $dateToValid = DateTime::createFromFormat(
        'Y-m-d',
        $dateTo
    );

    if ($dateToValid && $dateToValid->format('Y-m-d') === $dateTo) {
        $where[] = "l.created_at <= :date_to";
        $params['date_to'] = $dateTo . ' 23:59:59';
    }
}

$sql = "
    SELECT
        l.id,
        l.alert_id,
        l.action,
        l.details,
        l.created_at,
        a.title AS alert_title,
        a.external_id,
        a.source_id,
        s.name AS source_name
    FROM alert_logs l
    LEFT JOIN alerts a
        ON a.id = l.alert_id
    LEFT JOIN sources s
        ON s.id = a.source_id
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY l.created_at DESC, l.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("
    SELECT DISTINCT action
    FROM alert_logs
    WHERE action IS NOT NULL
      AND action <> ''
    ORDER BY action ASC
")->fetchAll();

$sources = $pdo->query("
    SELECT id, name
    FROM sources
    ORDER BY name ASC
")->fetchAll();

$totalLogs = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alert_logs
")->fetchColumn();

$todayLogs = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alert_logs
    WHERE created_at >= CURDATE()
")->fetchColumn();

$importLogs = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alert_logs
    WHERE action = 'rss_imported'
")->fetchColumn();

$lastLog = $pdo->query("
    SELECT created_at
    FROM alert_logs
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';

?>

<style>

.aw-log-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.aw-log-stat {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    padding: 17px;
}

.aw-log-stat-label {
    color: #6b7280;
    font-size: 12px;
    margin-bottom: 7px;
}

.aw-log-stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #111827;
}

.aw-log-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.aw-log-card h2 {
    margin: 0 0 18px;
    font-size: 19px;
}

.aw-filter-row {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) 170px 170px 150px 150px auto;
    gap: 8px;
    margin-bottom: 18px;
}

.aw-filter-row input,
.aw-filter-row select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #ffffff;
}

.aw-btn {
    display: inline-block;
    border: 0;
    border-radius: 7px;
    padding: 9px 13px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.aw-btn-primary {
    background: #2563eb;
    color: #ffffff;
}

.aw-btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.aw-log-table {
    width: 100%;
    border-collapse: collapse;
}

.aw-log-table th,
.aw-log-table td {
    padding: 11px 9px;
    border-bottom: 1px solid #eef0f3;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

.aw-log-table th {
    background: #f8fafc;
    color: #374151;
}

.aw-log-action {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 11px;
    font-weight: 700;
}

.aw-log-details {
    max-width: 420px;
    line-height: 1.45;
    color: #374151;
    word-break: break-word;
}

.aw-alert-title {
    font-weight: 700;
    color: #111827;
    max-width: 300px;
}

.aw-muted {
    color: #6b7280;
    font-size: 12px;
}

.aw-empty {
    text-align: center;
    padding: 35px;
    color: #6b7280;
}

.aw-log-id {
    font-family: monospace;
    color: #6b7280;
}

.aw-source-link {
    color: #2563eb;
    text-decoration: none;
}

.aw-source-link:hover {
    text-decoration: underline;
}

@media (max-width: 1200px) {

    .aw-log-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .aw-filter-row {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

}

@media (max-width: 700px) {

    .aw-log-stats {
        grid-template-columns: 1fr;
    }

    .aw-filter-row {
        grid-template-columns: 1fr;
    }

}

</style>

<div class="aw-page-heading">

    <div>

        <h1>
            System Logs
        </h1>

        <p class="aw-muted">
            Review alert processing and system activity records.
        </p>

    </div>

</div>

<div class="aw-log-stats">

    <div class="aw-log-stat">

        <div class="aw-log-stat-label">
            Total Log Entries
        </div>

        <div class="aw-log-stat-value">
            <?= $totalLogs ?>
        </div>

    </div>

    <div class="aw-log-stat">

        <div class="aw-log-stat-label">
            Today's Entries
        </div>

        <div class="aw-log-stat-value">
            <?= $todayLogs ?>
        </div>

    </div>

    <div class="aw-log-stat">

        <div class="aw-log-stat-label">
            RSS Imports
        </div>

        <div class="aw-log-stat-value">
            <?= $importLogs ?>
        </div>

    </div>

    <div class="aw-log-stat">

        <div class="aw-log-stat-label">
            Latest Activity
        </div>

        <div class="aw-log-stat-value" style="font-size:16px;">

            <?= $lastLog
                ? e(formatDateTime($lastLog))
                : 'None'
            ?>

        </div>

    </div>

</div>

<section class="aw-log-card">

    <h2>
        Activity Log
    </h2>

    <form
        method="get"
        class="aw-filter-row"
    >

        <input
            type="text"
            name="search"
            placeholder="Search action, details, alert..."
            value="<?= e($search) ?>"
        >

        <select name="action">

            <option value="">
                All Actions
            </option>

            <?php foreach ($actions as $action): ?>

                <option
                    value="<?= e($action['action']) ?>"
                    <?= $actionFilter === $action['action']
                        ? 'selected'
                        : ''
                    ?>
                >
                    <?= e($action['action']) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <select name="source_id">

            <option value="">
                All Sources
            </option>

            <?php foreach ($sources as $source): ?>

                <option
                    value="<?= (int)$source['id'] ?>"
                    <?= $sourceFilter === (int)$source['id']
                        ? 'selected'
                        : ''
                    ?>
                >
                    <?= e($source['name']) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <input
            type="date"
            name="date_from"
            value="<?= e($dateFrom) ?>"
        >

        <input
            type="date"
            name="date_to"
            value="<?= e($dateTo) ?>"
        >

        <button
            type="submit"
            class="aw-btn aw-btn-primary"
        >
            Filter
        </button>

    </form>

    <?php if (!$logs): ?>

        <div class="aw-empty">
            No log entries matched the selected filters.
        </div>

    <?php else: ?>

        <div style="overflow-x:auto;">

            <table class="aw-log-table">

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Date / Time
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Alert
                        </th>

                        <th>
                            Source
                        </th>

                        <th>
                            Details
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($logs as $log): ?>

                    <tr>

                        <td class="aw-log-id">

                            #<?= (int)$log['id'] ?>

                        </td>

                        <td>

                            <?= e(
                                formatDateTime(
                                    $log['created_at']
                                )
                            ) ?>

                        </td>

                        <td>

                            <span class="aw-log-action">

                                <?= e(
                                    $log['action']
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <?php if ($log['alert_id']): ?>

                                <div class="aw-alert-title">

                                    <?= e(
                                        $log['alert_title']
                                        ?? 'Alert #' . $log['alert_id']
                                    ) ?>

                                </div>

                                <div class="aw-muted">

                                    Alert ID:
                                    <?= (int)$log['alert_id'] ?>

                                </div>

                                <?php if ($log['external_id']): ?>

                                    <div class="aw-muted">

                                        External ID:
                                        <?= e(
                                            $log['external_id']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if ($log['source_name']): ?>

                                <a
                                    class="aw-source-link"
                                    href="<?= e(
                                        BASE_URL
                                    ) ?>/admin/sources.php?search=<?= urlencode(
                                        $log['source_name']
                                    ) ?>"
                                >
                                    <?= e(
                                        $log['source_name']
                                    ) ?>
                                </a>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="aw-log-details">

                                <?= e(
                                    $log['details']
                                    ?? '-'
                                ) ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>