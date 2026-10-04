<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Alert Management';

$error = '';
$success = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';

        if (
            $action === 'archive'
            || $action === 'restore'
        ) {

            $alertId = filter_var(
                $_POST['alert_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$alertId) {

                $error = 'Invalid alert selected.';

            } else {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        title,
                        status
                    FROM alerts
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    'id' => $alertId
                ]);

                $alert = $stmt->fetch();

                if (!$alert) {

                    $error = 'Alert not found.';

                } else {

                    $newStatus =
                        $action === 'archive'
                            ? 'archived'
                            : 'active';


                    $update = $pdo->prepare("
                        UPDATE alerts
                        SET
                            status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                    ");

                    $update->execute([
                        'status' => $newStatus,
                        'id' => $alertId
                    ]);


                    if ($newStatus === 'archived') {

                        $success =
                            'Alert archived successfully.';

                    } else {

                        $success =
                            'Alert restored successfully.';
                    }
                }
            }
        }
    }
}




$search = trim(
    $_GET['search'] ?? ''
);

$severityFilter = strtolower(
    trim($_GET['severity'] ?? '')
);

$statusFilter = strtolower(
    trim($_GET['status'] ?? '')
);

$categoryFilter = trim(
    $_GET['category'] ?? ''
);

$locationFilter = trim(
    $_GET['location'] ?? ''
);

$sourceIdFilter = filter_var(
    $_GET['source_id'] ?? '',
    FILTER_VALIDATE_INT
);



$allowedSeverities = [
    'low',
    'medium',
    'high',
    'critical'
];

$allowedStatuses = [
    'active',
    'archived'
];


if (
    !in_array(
        $severityFilter,
        $allowedSeverities,
        true
    )
) {

    $severityFilter = '';
}


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter = '';
}




$sources = $pdo->query("
    SELECT
        id,
        name
    FROM sources
    ORDER BY name ASC
")->fetchAll();



$categories = $pdo->query("
    SELECT DISTINCT
        category
    FROM alerts
    WHERE category IS NOT NULL
      AND category <> ''
    ORDER BY category ASC
")->fetchAll();




$where = [];

$params = [];




if ($search !== '') {

    $where[] = "
        (
            a.title LIKE :search
            OR a.description LIKE :search
            OR a.external_id LIKE :search
            OR a.category LIKE :search
            OR a.location LIKE :search
            OR s.name LIKE :search
        )
    ";

    $params['search'] =
        '%' . $search . '%';
}



if ($severityFilter !== '') {

    $where[] = "
        a.severity = :severity
    ";

    $params['severity'] =
        $severityFilter;
}



if ($statusFilter !== '') {

    $where[] = "
        a.status = :status
    ";

    $params['status'] =
        $statusFilter;
}


/*
|--------------------------------------------------------------------------
| CATEGORY
|--------------------------------------------------------------------------
*/

if ($categoryFilter !== '') {

    $where[] = "
        a.category = :category
    ";

    $params['category'] =
        $categoryFilter;
}



if ($locationFilter !== '') {

    $where[] = "
        a.location LIKE :location
    ";

    $params['location'] =
        '%' . $locationFilter . '%';
}




if ($sourceIdFilter) {

    $where[] = "
        a.source_id = :source_id
    ";

    $params['source_id'] =
        $sourceIdFilter;
}


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
        a.updated_at,

        s.name AS source_name,
        s.source_type

    FROM alerts a

    LEFT JOIN sources s
        ON s.id = a.source_id
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
    ORDER BY
        COALESCE(
            a.published_at,
            a.created_at
        ) DESC,
        a.id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$alerts = $stmt->fetchAll();




$totalAlerts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alerts
")->fetchColumn();


$activeAlerts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alerts
    WHERE status = 'active'
")->fetchColumn();


$archivedAlerts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alerts
    WHERE status = 'archived'
")->fetchColumn();


$criticalAlerts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alerts
    WHERE status = 'active'
      AND severity = 'critical'
")->fetchColumn();


$highAlerts = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alerts
    WHERE status = 'active'
      AND severity = 'high'
")->fetchColumn();


require_once __DIR__ . '/../includes/header.php';

?>


<style>

.aw-alert-stats {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 15px;

    margin-bottom: 22px;

}


.aw-alert-stat {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 11px;

    padding: 17px;

}


.aw-alert-stat-label {

    color: #6b7280;

    font-size: 12px;

    margin-bottom: 7px;

}


.aw-alert-stat-value {

    font-size: 26px;

    font-weight: 700;

    color: #111827;

}


.aw-alert-card {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.05);

}


.aw-alert-card h2 {

    margin: 0 0 18px;

    font-size: 19px;

}


.aw-filter-row {

    display: grid;

    grid-template-columns:
        minmax(180px, 1fr)
        135px
        135px
        160px
        150px
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


.aw-alert-message {

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


.aw-alert-table {

    width: 100%;

    border-collapse: collapse;

}


.aw-alert-table th,
.aw-alert-table td {

    padding: 11px 9px;

    border-bottom:
        1px solid #eef0f3;

    text-align: left;

    vertical-align: top;

    font-size: 13px;

}


.aw-alert-table th {

    background: #f8fafc;

    color: #374151;

}


.aw-alert-title {

    font-weight: 700;

    color: #111827;

    max-width: 360px;

}


.aw-alert-description {

    margin-top: 4px;

    color: #6b7280;

    font-size: 12px;

    max-width: 360px;

    line-height: 1.45;

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


.aw-status-active {

    background: #dcfce7;

    color: #166534;

}


.aw-status-archived {

    background: #e5e7eb;

    color: #374151;

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


.aw-source-link {

    color: #2563eb;

    text-decoration: none;

}


.aw-source-link:hover {

    text-decoration: underline;

}


.aw-external-id {

    font-family: monospace;

    color: #6b7280;

    font-size: 11px;

}


@media (max-width: 1250px) {

    .aw-alert-stats {

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

    }

    .aw-filter-row {

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

    }

}


@media (max-width: 750px) {

    .aw-alert-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

    .aw-filter-row {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 500px) {

    .aw-alert-stats {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="aw-page-heading">

    <div>

        <h1>
            Alert Management
        </h1>

        <p class="aw-muted">
            Review, search, filter and manage stored alert records.
        </p>

    </div>

</div>


<?php if ($error !== ''): ?>

    <div class="aw-alert-message aw-alert-error">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<?php if ($success !== ''): ?>

    <div class="aw-alert-message aw-alert-success">

        <?= e($success) ?>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
-->

<div class="aw-alert-stats">


    <div class="aw-alert-stat">

        <div class="aw-alert-stat-label">
            Total Alerts
        </div>

        <div class="aw-alert-stat-value">
            <?= $totalAlerts ?>
        </div>

    </div>


    <div class="aw-alert-stat">

        <div class="aw-alert-stat-label">
            Active
        </div>

        <div class="aw-alert-stat-value">
            <?= $activeAlerts ?>
        </div>

    </div>


    <div class="aw-alert-stat">

        <div class="aw-alert-stat-label">
            Archived
        </div>

        <div class="aw-alert-stat-value">
            <?= $archivedAlerts ?>
        </div>

    </div>


    <div class="aw-alert-stat">

        <div class="aw-alert-stat-label">
            Critical Active
        </div>

        <div class="aw-alert-stat-value">
            <?= $criticalAlerts ?>
        </div>

    </div>


    <div class="aw-alert-stat">

        <div class="aw-alert-stat-label">
            High Active
        </div>

        <div class="aw-alert-stat-value">
            <?= $highAlerts ?>
        </div>

    </div>


</div>


<section class="aw-alert-card">


    <h2>
        Stored Alerts
    </h2>


    <!--
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    -->

    <form
        method="get"
        class="aw-filter-row"
    >


        <input
            type="text"
            name="search"
            placeholder="Search title, description, ID, location..."
            value="<?= e($search) ?>"
        >


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


        <select name="status">

            <option value="">
                All Status
            </option>

            <?php foreach (
                $allowedStatuses
                as $status
            ): ?>

                <option
                    value="<?= e($status) ?>"
                    <?= $statusFilter === $status
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= e(
                        ucfirst($status)
                    ) ?>

                </option>

            <?php endforeach; ?>

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


        <select name="source_id">

            <option value="">
                All Sources
            </option>

            <?php foreach (
                $sources
                as $source
            ): ?>

                <option
                    value="<?= (int)$source['id'] ?>"
                    <?= $sourceIdFilter ===
                        (int)$source['id']
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= e(
                        $source['name']
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




    <form
        method="get"
        style="
            display:flex;
            gap:8px;
            margin-bottom:18px;
        "
    >

        <input
            type="hidden"
            name="search"
            value="<?= e($search) ?>"
        >

        <input
            type="hidden"
            name="severity"
            value="<?= e($severityFilter) ?>"
        >

        <input
            type="hidden"
            name="status"
            value="<?= e($statusFilter) ?>"
        >

        <input
            type="hidden"
            name="category"
            value="<?= e($categoryFilter) ?>"
        >

        <input
            type="hidden"
            name="source_id"
            value="<?= $sourceIdFilter
                ? (int)$sourceIdFilter
                : ''
            ?>"
        >

        <input
            type="text"
            name="location"
            placeholder="Location contains..."
            value="<?= e($locationFilter) ?>"
            style="
                max-width:300px;
                width:100%;
                padding:10px;
                border:1px solid #d1d5db;
                border-radius:7px;
            "
        >

        <button
            type="submit"
            class="aw-btn aw-btn-secondary"
        >
            Apply Location
        </button>


        <a
            href="<?= e(
                BASE_URL
            ) ?>/admin/alerts.php"
            class="aw-btn aw-btn-secondary"
        >
            Clear All
        </a>

    </form>



    <?php if (!$alerts): ?>

        <div class="aw-empty">

            No alerts matched the selected criteria.

        </div>

    <?php else: ?>

        <div style="overflow-x:auto;">

            <table class="aw-alert-table">

                <thead>

                    <tr>

                        <th>
                            Alert
                        </th>

                        <th>
                            Source
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
                            Published
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $alerts
                    as $alert
                ): ?>

                    <tr>


                        <!-- ALERT -->

                        <td>

                            <div class="aw-alert-title">

                                <?= e(
                                    $alert['title']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $alert['description']
                                )
                            ): ?>

                                <div class="aw-alert-description">

                                    <?= e(
                                        mb_substr(
                                            $alert['description'],
                                            0,
                                            180
                                        )
                                    ) ?>

                                    <?php if (
                                        mb_strlen(
                                            $alert['description']
                                        ) > 180
                                    ): ?>

                                        ...

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $alert['external_id']
                                )
                            ): ?>

                                <div class="aw-external-id">

                                    ID:
                                    <?= e(
                                        $alert['external_id']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- SOURCE -->

                        <td>

                            <?php if (
                                !empty(
                                    $alert['source_name']
                                )
                            ): ?>

                                <a
                                    href="<?= e(
                                        BASE_URL
                                    ) ?>/admin/sources.php?search=<?= urlencode(
                                        $alert['source_name']
                                    ) ?>"
                                    class="aw-source-link"
                                >

                                    <?= e(
                                        $alert['source_name']
                                    ) ?>

                                </a>

                            <?php else: ?>

                                -

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $alert['source_type']
                                )
                            ): ?>

                                <div class="aw-muted">

                                    <?= e(
                                        strtoupper(
                                            $alert['source_type']
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- CATEGORY -->

                        <td>

                            <?= e(
                                $alert['category']
                                ?? '-'
                            ) ?>

                        </td>


                        <!-- LOCATION -->

                        <td>

                            <?= e(
                                $alert['location']
                                ?? '-'
                            ) ?>

                        </td>


                        <!-- SEVERITY -->

                        <td>

                            <span
                                class="aw-badge
                                aw-severity-<?= e(
                                    $alert['severity']
                                ) ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $alert['severity']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- PUBLISHED -->

                        <td>

                            <?php if (
                                !empty(
                                    $alert['published_at']
                                )
                            ): ?>

                                <?= e(
                                    formatDateTime(
                                        $alert['published_at']
                                    )
                                ) ?>

                            <?php else: ?>

                                <span class="aw-muted">
                                    Not available
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="aw-badge
                                aw-status-<?= e(
                                    $alert['status']
                                ) ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $alert['status']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- ACTIONS -->

                        <td>

                            <div class="aw-actions">


                                <a
                                    href="<?= e(
                                        BASE_URL
                                    ) ?>/alerts/view.php?id=<?= (int)$alert['id'] ?>"
                                    class="aw-btn aw-btn-secondary"
                                >
                                    View
                                </a>


                                <?php if (
                                    $alert['status']
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
                                            value="archive"
                                        >


                                        <input
                                            type="hidden"
                                            name="alert_id"
                                            value="<?= (int)$alert['id'] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="aw-btn aw-btn-danger"
                                        >
                                            Archive
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
                                            value="restore"
                                        >


                                        <input
                                            type="hidden"
                                            name="alert_id"
                                            value="<?= (int)$alert['id'] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="aw-btn aw-btn-success"
                                        >
                                            Restore
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