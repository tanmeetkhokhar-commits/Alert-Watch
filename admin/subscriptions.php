<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Subscription Management';

$error = '';
$success = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';

        if (
            $action === 'activate'
            || $action === 'deactivate'
        ) {

            $subscriptionId = filter_var(
                $_POST['subscription_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$subscriptionId) {

                $error = 'Invalid subscription selected.';

            } else {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        status
                    FROM subscriptions
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    'id' => $subscriptionId
                ]);

                $subscription = $stmt->fetch();

                if (!$subscription) {

                    $error = 'Subscription not found.';

                } else {

                    $newStatus =
                        $action === 'activate'
                            ? 'active'
                            : 'inactive';


                    $update = $pdo->prepare("
                        UPDATE subscriptions
                        SET
                            status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                    ");

                    $update->execute([
                        'status' => $newStatus,
                        'id' => $subscriptionId
                    ]);


                    $success =
                        $newStatus === 'active'
                            ? 'Subscription activated successfully.'
                            : 'Subscription deactivated successfully.';
                }
            }
        }
    }
}




$search = trim(
    $_GET['search'] ?? ''
);

$statusFilter = strtolower(
    trim($_GET['status'] ?? '')
);

$notificationFilter = strtolower(
    trim($_GET['notification_method'] ?? '')
);

$categoryFilter = trim(
    $_GET['category'] ?? ''
);

$severityFilter = strtolower(
    trim($_GET['severity'] ?? '')
);




$allowedStatuses = [
    'active',
    'inactive'
];

$allowedMethods = [
    'dashboard',
    'email'
];

$allowedSeverities = [
    'low',
    'medium',
    'high',
    'critical'
];


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter = '';
}


if (
    !in_array(
        $notificationFilter,
        $allowedMethods,
        true
    )
) {

    $notificationFilter = '';
}


if (
    !in_array(
        $severityFilter,
        $allowedSeverities,
        true
    )
) {

    $severityFilter = '';
}



$where = [];

$params = [];


if ($search !== '') {

    $where[] = "
        (
            u.name LIKE :search
            OR u.email LIKE :search
            OR s.category LIKE :search
            OR s.location LIKE :search
        )
    ";

    $params['search'] =
        '%' . $search . '%';
}


if ($statusFilter !== '') {

    $where[] = "
        s.status = :status
    ";

    $params['status'] =
        $statusFilter;
}


if ($notificationFilter !== '') {

    $where[] = "
        s.notification_method = :notification_method
    ";

    $params['notification_method'] =
        $notificationFilter;
}


if ($categoryFilter !== '') {

    $where[] = "
        s.category = :category
    ";

    $params['category'] =
        $categoryFilter;
}


if ($severityFilter !== '') {

    $where[] = "
        s.severity = :severity
    ";

    $params['severity'] =
        $severityFilter;
}



$sql = "
    SELECT

        s.id,
        s.user_id,
        s.category,
        s.location,
        s.severity,
        s.notification_method,
        s.status,
        s.created_at,
        s.updated_at,

        u.name AS user_name,
        u.email AS user_email

    FROM subscriptions s

    INNER JOIN users u
        ON u.id = s.user_id
";


if ($where) {

    $sql .=
        ' WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


$sql .= "
    ORDER BY s.id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$subscriptions = $stmt->fetchAll();




$totalSubscriptions = (int)$pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
")->fetchColumn();


$activeSubscriptions = (int)$pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
    WHERE status = 'active'
")->fetchColumn();


$inactiveSubscriptions = (int)$pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
    WHERE status = 'inactive'
")->fetchColumn();


$emailSubscriptions = (int)$pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
    WHERE notification_method = 'email'
      AND status = 'active'
")->fetchColumn();


$dashboardSubscriptions = (int)$pdo->query("
    SELECT COUNT(*)
    FROM subscriptions
    WHERE notification_method = 'dashboard'
      AND status = 'active'
")->fetchColumn();




$categories = $pdo->query("
    SELECT DISTINCT category
    FROM subscriptions
    WHERE category IS NOT NULL
      AND category <> ''
    ORDER BY category ASC
")->fetchAll();


require_once __DIR__ . '/../includes/header.php';

?>


<style>

.aw-sub-stats {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 15px;

    margin-bottom: 22px;

}


.aw-sub-stat {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 11px;

    padding: 17px;

}


.aw-sub-stat-label {

    color: #6b7280;

    font-size: 12px;

    margin-bottom: 7px;

}


.aw-sub-stat-value {

    font-size: 26px;

    font-weight: 700;

    color: #111827;

}


.aw-sub-card {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.05);

}


.aw-sub-card h2 {

    margin: 0 0 18px;

    font-size: 19px;

}


.aw-filter-row {

    display: grid;

    grid-template-columns:
        minmax(180px, 1fr)
        140px
        150px
        150px
        160px
        auto;

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


.aw-btn-danger {

    background: #fee2e2;

    color: #991b1b;

}


.aw-btn-success {

    background: #dcfce7;

    color: #166534;

}


.aw-alert {

    padding: 12px 14px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 13px;

}


.aw-alert-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;

}


.aw-alert-success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;

}


.aw-sub-table {

    width: 100%;

    border-collapse: collapse;

}


.aw-sub-table th,
.aw-sub-table td {

    padding: 11px 9px;

    border-bottom:
        1px solid #eef0f3;

    text-align: left;

    vertical-align: top;

    font-size: 13px;

}


.aw-sub-table th {

    background: #f8fafc;

    color: #374151;

}


.aw-user-name {

    font-weight: 700;

    color: #111827;

}


.aw-muted {

    color: #6b7280;

    font-size: 12px;

}


.aw-badge {

    display: inline-block;

    padding: 4px 8px;

    border-radius: 999px;

    font-size: 11px;

    font-weight: 700;

    text-transform: capitalize;

}


.aw-status-active {

    background: #dcfce7;

    color: #166534;

}


.aw-status-inactive {

    background: #e5e7eb;

    color: #374151;

}


.aw-method-email {

    background: #dbeafe;

    color: #1d4ed8;

}


.aw-method-dashboard {

    background: #e0e7ff;

    color: #3730a3;

}


.aw-severity-low {

    background: #f3f4f6;

    color: #374151;

}


.aw-severity-medium {

    background: #fef3c7;

    color: #92400e;

}


.aw-severity-high {

    background: #fed7aa;

    color: #9a3412;

}


.aw-severity-critical {

    background: #fee2e2;

    color: #991b1b;

}


.aw-actions {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;

}


.aw-inline-form {

    display: inline;

}


.aw-empty {

    text-align: center;

    padding: 35px;

    color: #6b7280;

}


@media (max-width: 1250px) {

    .aw-sub-stats {

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

    }

    .aw-filter-row {

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

    }

}


@media (max-width: 750px) {

    .aw-sub-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

    .aw-filter-row {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 500px) {

    .aw-sub-stats {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="aw-page-heading">

    <div>

        <h1>
            Subscription Management
        </h1>

        <p class="aw-muted">
            Review and manage user alert subscriptions.
        </p>

    </div>

</div>


<?php if ($error !== ''): ?>

    <div class="aw-alert aw-alert-error">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<?php if ($success !== ''): ?>

    <div class="aw-alert aw-alert-success">

        <?= e($success) ?>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
-->

<div class="aw-sub-stats">


    <div class="aw-sub-stat">

        <div class="aw-sub-stat-label">
            Total
        </div>

        <div class="aw-sub-stat-value">
            <?= $totalSubscriptions ?>
        </div>

    </div>


    <div class="aw-sub-stat">

        <div class="aw-sub-stat-label">
            Active
        </div>

        <div class="aw-sub-stat-value">
            <?= $activeSubscriptions ?>
        </div>

    </div>


    <div class="aw-sub-stat">

        <div class="aw-sub-stat-label">
            Inactive
        </div>

        <div class="aw-sub-stat-value">
            <?= $inactiveSubscriptions ?>
        </div>

    </div>


    <div class="aw-sub-stat">

        <div class="aw-sub-stat-label">
            Email
        </div>

        <div class="aw-sub-stat-value">
            <?= $emailSubscriptions ?>
        </div>

    </div>


    <div class="aw-sub-stat">

        <div class="aw-sub-stat-label">
            Dashboard
        </div>

        <div class="aw-sub-stat-value">
            <?= $dashboardSubscriptions ?>
        </div>

    </div>


</div>


<section class="aw-sub-card">


    <h2>
        User Subscriptions
    </h2>




    <form
        method="get"
        class="aw-filter-row"
    >


        <input
            type="text"
            name="search"
            placeholder="Search user, email, category, location..."
            value="<?= e($search) ?>"
        >


        <select name="status">

            <option value="">
                All Status
            </option>

            <option
                value="active"
                <?= $statusFilter === 'active'
                    ? 'selected'
                    : ''
                ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= $statusFilter === 'inactive'
                    ? 'selected'
                    : ''
                ?>
            >
                Inactive
            </option>

        </select>


        <select name="notification_method">

            <option value="">
                All Methods
            </option>

            <option
                value="dashboard"
                <?= $notificationFilter === 'dashboard'
                    ? 'selected'
                    : ''
                ?>
            >
                Dashboard
            </option>

            <option
                value="email"
                <?= $notificationFilter === 'email'
                    ? 'selected'
                    : ''
                ?>
            >
                Email
            </option>

        </select>


        <select name="category">

            <option value="">
                All Categories
            </option>

            <?php foreach (
                $categories
                as $category
            ): ?>

                <option
                    value="<?= e(
                        $category['category']
                    ) ?>"
                    <?= $categoryFilter ===
                        $category['category']
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= e(
                        $category['category']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


        <select name="severity">

            <option value="">
                All Severity
            </option>

            <?php foreach (
                $allowedSeverities
                as $severity
            ): ?>

                <option
                    value="<?= e($severity) ?>"
                    <?= $severityFilter === $severity
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= e(
                        ucfirst($severity)
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="submit"
            class="aw-btn aw-btn-primary"
        >
            Filter
        </button>


    </form>



    <?php if (!$subscriptions): ?>

        <div class="aw-empty">

            No subscriptions matched the selected filters.

        </div>

    <?php else: ?>

        <div style="overflow-x:auto;">

            <table class="aw-sub-table">

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Location
                        </th>

                        <th>
                            Severity
                        </th>

                        <th>
                            Notification
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $subscriptions
                    as $subscription
                ): ?>

                    <tr>


                        <td>

                            #<?= (int)$subscription['id'] ?>

                        </td>


                        <td>

                            <div class="aw-user-name">

                                <?= e(
                                    $subscription['user_name']
                                ) ?>

                            </div>

                            <div class="aw-muted">

                                <?= e(
                                    $subscription['user_email']
                                ) ?>

                            </div>

                            <div class="aw-muted">

                                User ID:
                                <?= (int)$subscription['user_id'] ?>

                            </div>

                        </td>


                        <td>

                            <?= e(
                                $subscription['category']
                                ?: 'All'
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $subscription['location']
                                ?: 'All'
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                $subscription['severity']
                            ): ?>

                                <span
                                    class="aw-badge
                                    aw-severity-<?= e(
                                        $subscription['severity']
                                    ) ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $subscription['severity']
                                        )
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="aw-muted">
                                    Any
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span
                                class="aw-badge
                                aw-method-<?= e(
                                    $subscription[
                                        'notification_method'
                                    ]
                                ) ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $subscription[
                                            'notification_method'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="aw-badge
                                aw-status-<?= e(
                                    $subscription['status']
                                ) ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $subscription['status']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                formatDateTime(
                                    $subscription['created_at']
                                )
                            ) ?>

                        </td>


                        <td>

                            <div class="aw-actions">


                                <?php if (
                                    $subscription['status']
                                    === 'active'
                                ): ?>


                                    <form
                                        method="post"
                                        class="aw-inline-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(
                                                csrfToken()
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate"
                                        >

                                        <input
                                            type="hidden"
                                            name="subscription_id"
                                            value="<?= (int)$subscription['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="aw-btn aw-btn-danger"
                                        >
                                            Deactivate
                                        </button>

                                    </form>


                                <?php else: ?>


                                    <form
                                        method="post"
                                        class="aw-inline-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(
                                                csrfToken()
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="activate"
                                        >

                                        <input
                                            type="hidden"
                                            name="subscription_id"
                                            value="<?= (int)$subscription['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="aw-btn aw-btn-success"
                                        >
                                            Activate
                                        </button>

                                    </form>


                                <?php endif; ?>


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