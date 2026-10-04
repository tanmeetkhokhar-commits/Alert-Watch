<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Source Management';

$error = '';
$success = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_status') {

            $sourceId = filter_var(
                $_POST['source_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$sourceId) {

                $error = 'Invalid source selected.';

            } else {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        name,
                        status
                    FROM sources
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    'id' => $sourceId
                ]);

                $source = $stmt->fetch();

                if (!$source) {

                    $error = 'Source not found.';

                } else {

                    $newStatus =
                        $source['status'] === 'active'
                            ? 'inactive'
                            : 'active';

                    $update = $pdo->prepare("
                        UPDATE sources
                        SET
                            status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                    ");

                    $update->execute([
                        'status' => $newStatus,
                        'id' => $sourceId
                    ]);

                    if ($newStatus === 'active') {

                        $success =
                            'Source "' .
                            $source['name'] .
                            '" has been activated.';

                    } else {

                        $success =
                            'Source "' .
                            $source['name'] .
                            '" has been deactivated.';
                    }
                }
            }
        }
    }
}




$search = trim($_GET['search'] ?? '');

$typeFilter = $_GET['type'] ?? '';

$statusFilter = $_GET['status'] ?? '';

$categoryFilter = trim($_GET['category'] ?? '');


$sql = "
    SELECT
        id,
        name,
        source_type,
        url,
        category,
        status,
        last_fetched,
        created_at,
        updated_at
    FROM sources
    WHERE 1 = 1
";

$params = [];



if ($search !== '') {

    $sql .= "
        AND (
            name LIKE :search
            OR url LIKE :search
            OR category LIKE :search
        )
    ";

    $params['search'] = '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| TYPE FILTER
|--------------------------------------------------------------------------
*/

if (in_array($typeFilter, ['rss', 'api'], true)) {

    $sql .= "
        AND source_type = :source_type
    ";

    $params['source_type'] = $typeFilter;
}


if (in_array($statusFilter, ['active', 'inactive'], true)) {

    $sql .= "
        AND status = :status
    ";

    $params['status'] = $statusFilter;
}



if ($categoryFilter !== '') {

    $sql .= "
        AND category LIKE :category
    ";

    $params['category'] = '%' . $categoryFilter . '%';
}


$sql .= "
    ORDER BY id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$sources = $stmt->fetchAll();




$totalSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
")->fetchColumn();


$activeSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
    WHERE status = 'active'
")->fetchColumn();


$inactiveSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
    WHERE status = 'inactive'
")->fetchColumn();


$rssSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
    WHERE source_type = 'rss'
")->fetchColumn();


$apiSources = (int) $pdo->query("
    SELECT COUNT(*)
    FROM sources
    WHERE source_type = 'api'
")->fetchColumn();


require_once __DIR__ . '/../includes/header.php';

?>


<style>

.aw-source-stats {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 15px;

    margin-bottom: 22px;

}


.aw-source-stat {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 11px;

    padding: 17px;

}


.aw-source-stat-label {

    color: #6b7280;

    font-size: 12px;

    margin-bottom: 7px;

}


.aw-source-stat-value {

    font-size: 26px;

    font-weight: 700;

    color: #111827;

}


.aw-source-card {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.05);

}


.aw-source-card h2 {

    margin: 0 0 18px;

    font-size: 19px;

}


.aw-source-actions {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;

    margin-bottom: 18px;

}


.aw-filter-row {

    display: grid;

    grid-template-columns:
        minmax(180px, 1fr)
        140px
        140px
        180px
        auto;

    gap: 9px;

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


.aw-source-table {

    width: 100%;

    border-collapse: collapse;

}


.aw-source-table th,
.aw-source-table td {

    padding: 11px 9px;

    border-bottom:
        1px solid #eef0f3;

    text-align: left;

    vertical-align: top;

    font-size: 13px;

}


.aw-source-table th {

    background: #f8fafc;

    color: #374151;

}


.aw-source-name {

    font-weight: 700;

    color: #111827;

}


.aw-muted {

    color: #6b7280;

    font-size: 12px;

}


.aw-source-url {

    max-width: 280px;

    word-break: break-word;

}


.aw-source-url a {

    color: #2563eb;

    text-decoration: none;

}


.aw-source-url a:hover {

    text-decoration: underline;

}


.aw-badge {

    display: inline-block;

    padding: 4px 8px;

    border-radius: 999px;

    font-size: 11px;

    font-weight: 700;

}


.aw-badge-active {

    background: #dcfce7;

    color: #166534;

}


.aw-badge-inactive {

    background: #e5e7eb;

    color: #374151;

}


.aw-badge-rss {

    background: #dbeafe;

    color: #1d4ed8;

}


.aw-badge-api {

    background: #f3e8ff;

    color: #7e22ce;

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


@media (max-width: 1150px) {

    .aw-source-stats {

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

    }

    .aw-filter-row {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

}


@media (max-width: 750px) {

    .aw-source-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

    .aw-filter-row {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 500px) {

    .aw-source-stats {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="aw-page-heading">

    <div>

        <h1>
            Source Management
        </h1>

        <p class="aw-muted">
            Monitor and manage configured AlertWatch data sources.
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




<div class="aw-source-stats">


    <div class="aw-source-stat">

        <div class="aw-source-stat-label">
            Total Sources
        </div>

        <div class="aw-source-stat-value">
            <?= $totalSources ?>
        </div>

    </div>


    <div class="aw-source-stat">

        <div class="aw-source-stat-label">
            Active Sources
        </div>

        <div class="aw-source-stat-value">
            <?= $activeSources ?>
        </div>

    </div>


    <div class="aw-source-stat">

        <div class="aw-source-stat-label">
            Inactive Sources
        </div>

        <div class="aw-source-stat-value">
            <?= $inactiveSources ?>
        </div>

    </div>


    <div class="aw-source-stat">

        <div class="aw-source-stat-label">
            RSS Sources
        </div>

        <div class="aw-source-stat-value">
            <?= $rssSources ?>
        </div>

    </div>


    <div class="aw-source-stat">

        <div class="aw-source-stat-label">
            API Sources
        </div>

        <div class="aw-source-stat-value">
            <?= $apiSources ?>
        </div>

    </div>


</div>


<section class="aw-source-card">


    <h2>
        Configured Sources
    </h2>



    <div class="aw-source-actions">

        <a
            href="<?= e(BASE_URL) ?>/sources/add.php"
            class="aw-btn aw-btn-primary"
        >
            + Add Source
        </a>


        <a
            href="<?= e(BASE_URL) ?>/sources/index.php"
            class="aw-btn aw-btn-secondary"
        >
            Open Source Manager
        </a>


        <a
            href="<?= e(BASE_URL) ?>/rss/scheduler.php"
            class="aw-btn aw-btn-secondary"
        >
            Run RSS Scheduler
        </a>

    </div>


 
    <form
        method="get"
        class="aw-filter-row"
    >


        <input
            type="text"
            name="search"
            placeholder="Search name, URL or category..."
            value="<?= e($search) ?>"
        >


        <select name="type">

            <option value="">
                All Types
            </option>

            <option
                value="rss"
                <?= $typeFilter === 'rss'
                    ? 'selected'
                    : ''
                ?>
            >
                RSS
            </option>

            <option
                value="api"
                <?= $typeFilter === 'api'
                    ? 'selected'
                    : ''
                ?>
            >
                API
            </option>

        </select>


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


        <input
            type="text"
            name="category"
            placeholder="Category..."
            value="<?= e($categoryFilter) ?>"
        >


        <button
            type="submit"
            class="aw-btn aw-btn-primary"
        >
            Filter
        </button>


    </form>



    <?php if (!$sources): ?>

        <div class="aw-empty">

            No sources matched the selected filters.

        </div>

    <?php else: ?>

        <div style="overflow-x:auto;">

            <table class="aw-source-table">

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Source
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            URL
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Last Fetched
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($sources as $source): ?>

                    <tr>


                        <td>

                            <?= (int)$source['id'] ?>

                        </td>


                        <td>

                            <div class="aw-source-name">

                                <?= e(
                                    $source['name']
                                ) ?>

                            </div>

                            <div class="aw-muted">

                                Created:
                                <?= e(
                                    formatDateTime(
                                        $source['created_at']
                                    )
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <span
                                class="aw-badge
                                <?= $source['source_type'] === 'rss'
                                    ? 'aw-badge-rss'
                                    : 'aw-badge-api'
                                ?>"
                            >

                                <?= e(
                                    strtoupper(
                                        $source['source_type']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                $source['category']
                                ?? '-'
                            ) ?>

                        </td>


                        <td class="aw-source-url">

                            <?php if (
                                !empty(
                                    $source['url']
                                )
                            ): ?>

                                <a
                                    href="<?= e(
                                        $source['url']
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >

                                    <?= e(
                                        $source['url']
                                    ) ?>

                                </a>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <span
                                class="aw-badge
                                <?= $source['status'] === 'active'
                                    ? 'aw-badge-active'
                                    : 'aw-badge-inactive'
                                ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $source['status']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $source['last_fetched']
                                )
                            ): ?>

                                <?= e(
                                    formatDateTime(
                                        $source['last_fetched']
                                    )
                                ) ?>

                            <?php else: ?>

                                <span class="aw-muted">
                                    Never
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="aw-actions">


                                <!-- EDIT -->

                                <a
                                    href="<?= e(
                                        BASE_URL
                                    ) ?>/sources/edit.php?id=<?= (int)$source['id'] ?>"
                                    class="aw-btn aw-btn-secondary"
                                >
                                    Edit
                                </a>


                                <!-- TOGGLE STATUS -->

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
                                        value="toggle_status"
                                    >


                                    <input
                                        type="hidden"
                                        name="source_id"
                                        value="<?= (int)$source['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="aw-btn
                                        <?= $source['status'] === 'active'
                                            ? 'aw-btn-danger'
                                            : 'aw-btn-success'
                                        ?>"
                                    >

                                        <?= $source['status'] === 'active'
                                            ? 'Deactivate'
                                            : 'Activate'
                                        ?>

                                    </button>

                                </form>


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