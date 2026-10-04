<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();


$stmt = $pdo->query("
    SELECT
        id,
        name,
        source_type,
        url,
        category,
        status,
        last_fetched,
        created_at
    FROM sources
    ORDER BY id DESC
");

$sources = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Sources - <?php echo e(APP_NAME); ?>
    </title>

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

        .navbar {
            height: 60px;
            background: #1f2937;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
        }

        .brand {
            font-size: 21px;
            font-weight: bold;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        .container {
            max-width: 1400px;
            margin: auto;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-header h2 {
            margin: 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .btn-fetch {
            background: #16a34a;
            color: white;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
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
            vertical-align: middle;
        }

        th {
            background: #f9fafb;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .source-url {
            max-width: 300px;
            word-break: break-word;
        }

        .source-url a {
            color: #2563eb;
            text-decoration: none;
        }

        .source-url a:hover {
            text-decoration: underline;
        }

        .empty-message {
            text-align: center;
            color: #6b7280;
            padding: 30px;
        }

        @media (max-width: 800px) {

            .navbar {
                padding: 0 15px;
            }

            .container {
                padding: 15px;
            }

            .page-header {
                align-items: flex-start;
                gap: 15px;
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<nav class="navbar">

    <div class="brand">
        AlertWatch
    </div>

    <div>

        <span>
            <?php echo e(currentUserName()); ?>
        </span>

        <a href="<?php echo BASE_URL; ?>/index.php">
            Dashboard
        </a>

        <a href="<?php echo BASE_URL; ?>/logout.php">
            Logout
        </a>

    </div>

</nav>


<main class="container">

    <div class="page-header">

        <h2>
            Alert Sources
        </h2>

        <a
            href="add.php"
            class="btn btn-primary"
        >
            + Add Source
        </a>

    </div>


    <div class="card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>URL</th>
                        <th>Status</th>
                        <th>Last Fetched</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (empty($sources)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty-message"
                        >
                            No sources found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($sources as $source): ?>

                        <tr>

                            <td>
                                <?php echo e($source['id']); ?>
                            </td>


                            <td>
                                <?php echo e($source['name']); ?>
                            </td>


                            <td>

                                <?php
                                echo e(
                                    strtoupper(
                                        $source['source_type']
                                    )
                                );
                                ?>

                            </td>


                            <td>
                                <?php echo e($source['category']); ?>
                            </td>


                            <td class="source-url">

                                <?php if (!empty($source['url'])): ?>

                                    <a
                                        href="<?php
                                            echo e($source['url']);
                                        ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <?php
                                        echo e($source['url']);
                                        ?>
                                    </a>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="badge <?php

                                    echo $source['status'] === 'active'
                                        ? 'active'
                                        : 'inactive';

                                ?>">

                                    <?php

                                    echo e(
                                        ucfirst(
                                            $source['status']
                                        )
                                    );

                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php

                                echo e(
                                    formatDateTime(
                                        $source['last_fetched']
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <div class="actions">


                                    <!-- Edit -->

                                    <a
                                        href="edit.php?id=<?php
                                            echo (int) $source['id'];
                                        ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>


                                    <!-- Fetch -->

                                    <a
                                        href="fetch.php?id=<?php
                                            echo (int) $source['id'];
                                        ?>"
                                        class="btn btn-fetch"
                                        onclick="return confirm(
                                            'Fetch alerts from this source now?'
                                        );"
                                    >
                                        Fetch
                                    </a>


                                    <!-- Delete -->

                                    <a
                                        href="delete.php?id=<?php
                                            echo (int) $source['id'];
                                        ?>"
                                        class="btn btn-delete"
                                        onclick="return confirm(
                                            'Are you sure you want to delete this source? Associated alerts may also be deleted.'
                                        );"
                                    >
                                        Delete
                                    </a>


                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

</body>

</html>