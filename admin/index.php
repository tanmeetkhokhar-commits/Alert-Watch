<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';



$totalUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
")->fetchColumn();

$activeUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE status = 'active'
")->fetchColumn();


$totalAlerts = (int) $pdo->query("
    SELECT COUNT(*)
    FROM alerts
")->fetchColumn();

$activeAlerts = (int) $pdo->query("
    SELECT COUNT(*)
    FROM alerts
    WHERE status = 'active'
")->fetchColumn();

$criticalAlerts = (int) $pdo->query("
    SELECT COUNT(*)
    FROM alerts
    WHERE status = 'active'
      AND severity = 'critical'
")->fetchColumn();




$totalSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
")->fetchColumn();

$activeSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
    WHERE status = 'active'
")->fetchColumn();



$totalSubscriptions = (int) $pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
")->fetchColumn();

$activeSubscriptions = (int) $pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
    WHERE status = 'active'
")->fetchColumn();




$recentLogs = $pdo->query("
    SELECT
        l.id,
        l.alert_id,
        l.action,
        l.details,
        l.created_at,
        a.title AS alert_title
    FROM alert_logs l
    LEFT JOIN alerts a
        ON a.id = l.alert_id
    ORDER BY l.id DESC
    LIMIT 8
")->fetchAll();




$recentUsers = $pdo->query("
    SELECT
        id,
        name,
        email,
        role,
        status,
        created_at
    FROM users
    ORDER BY id DESC
    LIMIT 5
")->fetchAll();


require_once __DIR__ . '/../includes/header.php';

?>

<style>

.aw-admin-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.aw-stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.aw-stat-label {
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 8px;
}

.aw-stat-value {
    font-size: 30px;
    font-weight: 700;
    color: #111827;
}

.aw-stat-meta {
    margin-top: 7px;
    color: #6b7280;
    font-size: 12px;
}

.aw-admin-sections {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 20px;
}

.aw-admin-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    overflow-x: auto;
}

.aw-admin-card h2 {
    margin: 0 0 16px;
    font-size: 19px;
}

.aw-admin-table {
    width: 100%;
    border-collapse: collapse;
}

.aw-admin-table th,
.aw-admin-table td {
    padding: 11px 9px;
    border-bottom: 1px solid #eef0f3;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

.aw-admin-table th {
    background: #f8fafc;
    color: #374151;
}

.aw-muted {
    color: #6b7280;
}

.aw-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.aw-active {
    background: #dcfce7;
    color: #166534;
}

.aw-inactive {
    background: #e5e7eb;
    color: #374151;
}

.aw-critical {
    background: #fee2e2;
    color: #991b1b;
}

.aw-admin-links {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 24px;
}

.aw-admin-link {
    display: block;
    padding: 13px 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    text-decoration: none;
    color: #1f2937;
    font-weight: 600;
    font-size: 13px;
}

.aw-admin-link:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.aw-admin-heading {
    margin-bottom: 22px;
}

.aw-admin-heading h1 {
    margin: 0 0 5px;
}

@media (max-width: 1050px) {

    .aw-admin-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .aw-admin-sections {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 650px) {

    .aw-admin-grid,
    .aw-admin-links {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="aw-admin-heading">

    <h1>
        Administrator Dashboard
    </h1>

    <p class="aw-muted">
        System overview and administration controls.
    </p>

</div>


<div class="aw-admin-links">

    <a
        class="aw-admin-link"
        href="<?= e(BASE_URL) ?>/admin/users.php"
    >
        User Management
    </a>


    <a
        class="aw-admin-link"
        href="<?= e(BASE_URL) ?>/admin/sources.php"
    >
        Source Management
    </a>


    <a
        class="aw-admin-link"
        href="<?= e(BASE_URL) ?>/admin/alerts.php"
    >
        Alert Management
    </a>


    <a
        class="aw-admin-link"
        href="<?= e(BASE_URL) ?>/admin/subscriptions.php"
    >
        Subscription Management
    </a>


    <a
        class="aw-admin-link"
        href="<?= e(BASE_URL) ?>/admin/system_logs.php"
    >
        System Logs
    </a>


    <a
        class="aw-admin-link"
        href="<?= e(BASE_URL) ?>/admin/settings.php"
    >
        System Settings
    </a>

</div>


<div class="aw-admin-grid">


    <div class="aw-stat-card">

        <div class="aw-stat-label">
            Total Users
        </div>

        <div class="aw-stat-value">
            <?= $totalUsers ?>
        </div>

        <div class="aw-stat-meta">
            <?= $activeUsers ?> active
        </div>

    </div>


    <div class="aw-stat-card">

        <div class="aw-stat-label">
            Alerts
        </div>

        <div class="aw-stat-value">
            <?= $totalAlerts ?>
        </div>

        <div class="aw-stat-meta">

            <?= $activeAlerts ?> active

            <?php if ($criticalAlerts > 0): ?>

                · <?= $criticalAlerts ?> critical

            <?php endif; ?>

        </div>

    </div>


    <div class="aw-stat-card">

        <div class="aw-stat-label">
            Sources
        </div>

        <div class="aw-stat-value">
            <?= $totalSources ?>
        </div>

        <div class="aw-stat-meta">
            <?= $activeSources ?> active
        </div>

    </div>


    <div class="aw-stat-card">

        <div class="aw-stat-label">
            Subscriptions
        </div>

        <div class="aw-stat-value">
            <?= $totalSubscriptions ?>
        </div>

        <div class="aw-stat-meta">
            <?= $activeSubscriptions ?> active
        </div>

    </div>


</div>


<div class="aw-admin-sections">


    <!-- RECENT SYSTEM ACTIVITY -->

    <section class="aw-admin-card">

        <h2>
            Recent System Activity
        </h2>


        <?php if (!$recentLogs): ?>

            <p class="aw-muted">
                No system activity has been recorded yet.
            </p>

        <?php else: ?>

            <table class="aw-admin-table">

                <thead>

                    <tr>

                        <th>
                            Time
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Alert
                        </th>

                        <th>
                            Details
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($recentLogs as $log): ?>

                    <tr>

                        <td>

                            <?= e(
                                formatDateTime(
                                    $log['created_at']
                                )
                            ) ?>

                        </td>


                        <td>

                            <strong>
                                <?= e($log['action']) ?>
                            </strong>

                        </td>


                        <td>

                            <?php if (!empty($log['alert_id'])): ?>

                                #<?= (int)$log['alert_id'] ?>

                                <div class="aw-muted">

                                    <?= e(
                                        $log['alert_title']
                                        ?? 'Alert'
                                    ) ?>

                                </div>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                $log['details']
                                ?? '-'
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </section>



    <!-- RECENT USERS -->

    <section class="aw-admin-card">

        <h2>
            Recent Users
        </h2>


        <?php if (!$recentUsers): ?>

            <p class="aw-muted">
                No users found.
            </p>

        <?php else: ?>

            <table class="aw-admin-table">

                <thead>

                    <tr>

                        <th>
                            User
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Created
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($recentUsers as $user): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= e($user['name']) ?>
                            </strong>

                            <div class="aw-muted">

                                <?= e(
                                    $user['email']
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?= e(
                                $user['role']
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="aw-badge
                                <?= $user['status'] === 'active'
                                    ? 'aw-active'
                                    : 'aw-inactive'
                                ?>"
                            >

                                <?= e(
                                    $user['status']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                formatDateTime(
                                    $user['created_at']
                                )
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </section>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>