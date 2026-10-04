<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: preferences.php');
    exit;
}

$category = trim($_POST['category'] ?? '');
$location = trim($_POST['location'] ?? '');
$severity = strtolower(trim($_POST['severity'] ?? ''));
$notificationMethod = strtolower(trim($_POST['notification_method'] ?? 'dashboard'));

$allowedSeverity = ['low', 'medium', 'high', 'critical'];
$allowedMethods = ['dashboard', 'email'];

if ($severity !== '' && !in_array($severity, $allowedSeverity, true)) {
    header('Location: preferences.php?error=' . urlencode('Invalid severity selected.'));
    exit;
}

if (!in_array($notificationMethod, $allowedMethods, true)) {
    header('Location: preferences.php?error=' . urlencode('Invalid notification method selected.'));
    exit;
}

if (mb_strlen($category) > 100 || mb_strlen($location) > 255) {
    header('Location: preferences.php?error=' . urlencode('Category or location is too long.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM subscriptions
    WHERE user_id = :user_id
      AND COALESCE(category, '') = :category
      AND COALESCE(location, '') = :location
      AND COALESCE(severity, '') = :severity
      AND status = 'active'
    LIMIT 1
");
$stmt->execute([
    'user_id' => $userId,
    'category' => $category,
    'location' => $location,
    'severity' => $severity
]);

$existing = $stmt->fetch();

if ($existing) {
    $update = $pdo->prepare("
        UPDATE subscriptions
        SET notification_method = :notification_method, updated_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");
    $update->execute([
        'notification_method' => $notificationMethod,
        'id' => $existing['id'],
        'user_id' => $userId
    ]);

    header('Location: preferences.php?success=' . urlencode('Existing subscription updated.'));
    exit;
}

$insert = $pdo->prepare("
    INSERT INTO subscriptions
        (user_id, category, location, severity, notification_method, status, created_at, updated_at)
    VALUES
        (:user_id, :category, :location, :severity, :notification_method, 'active', NOW(), NOW())
");

$insert->execute([
    'user_id' => $userId,
    'category' => $category !== '' ? $category : null,
    'location' => $location !== '' ? $location : null,
    'severity' => $severity !== '' ? $severity : null,
    'notification_method' => $notificationMethod
]);

header('Location: preferences.php?success=' . urlencode('Subscription created successfully.'));
exit;
