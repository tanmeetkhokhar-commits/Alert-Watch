<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM alerts
    WHERE status = 'active'
");

$totalAlerts = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM alerts
    WHERE status = 'active'
    AND severity = 'critical'
");

$criticalAlerts = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM sources
    WHERE status = 'active'
");

$totalSources = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM subscriptions
    WHERE status = 'active'
");

$totalSubscriptions = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT
        a.id,
        a.title,
        a.category,
        a.location,
        a.severity,
        a.published_at,
        s.name AS source_name
    FROM alerts a
    INNER JOIN sources s
        ON a.source_id = s.id
    WHERE a.status = 'active'
    ORDER BY a.published_at DESC
    LIMIT 10
");

$latestAlerts = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<style>
* {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }
.navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
.welcome {
            margin-bottom: 25px;
        }

        .welcome h2 {
            margin: 0 0 5px;
        }

        .welcome p {
            color: #6b7280;
        }

        .cards {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 8px;
            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .card-title {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 30px;
            font-weight: bold;
        }

        .critical {
            color: #dc2626;
        }

        .section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .section h3 {
            margin-top: 0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-low {
            background: #dcfce7;
            color: #166534;
        }

        .badge-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-high {
            background: #fed7aa;
            color: #9a3412;
        }

        .badge-critical {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 900px) {

            .cards {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

            .cards {
                grid-template-columns: 1fr;
            }
}
</style>

<div class="dashboard-content">
<main class="container">

    <div class="welcome">

        <h2>
            AlertWatch Dashboard
        </h2>

        <p>
            Welcome,
            <?php echo e(currentUserName()); ?>.
        </p>

    </div>


    <div class="cards">

        <div class="card">

            <div class="card-title">
                Active Alerts
            </div>

            <div class="card-value">
                <?php echo $totalAlerts; ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Critical Alerts
            </div>

            <div class="card-value critical">
                <?php echo $criticalAlerts; ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Active Sources
            </div>

            <div class="card-value">
                <?php echo $totalSources; ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Subscriptions
            </div>

            <div class="card-value">
                <?php echo $totalSubscriptions; ?>
            </div>

        </div>

    </div>


    <div class="section">

        <h3>
            Latest Alerts
        </h3>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Title</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Severity</th>
                        <th>Source</th>
                        <th>Published</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($latestAlerts)): ?>

                    <tr>

                        <td colspan="6">
                            No alerts available.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($latestAlerts as $alert): ?>

                        <tr>

                            <td>
                                <?php echo e($alert['title']); ?>
                            </td>

                            <td>
                                <?php echo e($alert['category']); ?>
                            </td>

                            <td>
                                <?php echo e($alert['location']); ?>
                            </td>

                            <td>

                                <span class="badge badge-<?php
                                    echo e($alert['severity']);
                                ?>">
                                    <?php
                                    echo e(
                                        ucfirst(
                                            $alert['severity']
                                        )
                                    );
                                    ?>
                                </span>

                            </td>

                            <td>
                                <?php
                                echo e(
                                    $alert['source_name']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    formatDateTime(
                                        $alert['published_at']
                                    )
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>
</div>

<?php
require __DIR__ . '/includes/footer.php';
?>
