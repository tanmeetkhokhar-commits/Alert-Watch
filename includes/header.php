<?php
if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = 'AlertWatch';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> - AlertWatch</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .aw-navbar {
            width: 100%;
            background: #1f3042;
            color: #ffffff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }

        .aw-nav-inner {
            width: 100%;
            min-height: 62px;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .aw-brand {
            color: #ffffff;
            text-decoration: none;
            font-size: 21px;
            font-weight: 700;
            white-space: nowrap;
            margin-right: auto;
        }

        .aw-nav-links {
            display: flex;
            align-items: stretch;
            min-height: 62px;
            gap: 2px;
        }

        .aw-nav-links a,
        .aw-nav-user a {
            display: flex;
            align-items: center;
            color: #ffffff;
            text-decoration: none;
            padding: 0 13px;
            font-size: 15px;
            white-space: nowrap;
        }

        .aw-nav-links a:hover,
        .aw-nav-user a:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .aw-nav-links a.active {
            background: rgba(255, 255, 255, 0.14);
            font-weight: 600;
        }

        .aw-nav-user {
            display: flex;
            align-items: center;
            min-height: 62px;
            gap: 4px;
        }

        .aw-user-name {
            color: #dbe5ee;
            font-size: 14px;
            padding: 0 8px;
            white-space: nowrap;
        }

        .aw-page-container {
            width: min(1100px, calc(100% - 40px));
            margin: 34px auto;
        }

        @media (max-width: 900px) {
            .aw-nav-inner {
                flex-wrap: wrap;
                padding: 10px 16px;
                gap: 4px;
            }

            .aw-brand {
                width: 100%;
                min-height: 34px;
                margin-right: 0;
            }

            .aw-nav-links,
            .aw-nav-user {
                min-height: 44px;
            }

            .aw-nav-links a,
            .aw-nav-user a {
                min-height: 44px;
                padding: 0 9px;
                font-size: 14px;
            }

            .aw-user-name {
                display: none;
            }

            .aw-page-container {
                width: min(100% - 24px, 1100px);
                margin: 20px auto;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . '/navbar.php'; ?>

<main class="aw-page-container">
