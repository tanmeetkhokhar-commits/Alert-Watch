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

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: preferences.php?error=' . urlencode('Invalid subscription ID.'));
    exit;
}

$stmt = $pdo->prepare("
    UPDATE subscriptions
    SET status = 'inactive', updated_at = NOW()
    WHERE id = :id AND user_id = :user_id AND status = 'active'
");
$stmt->execute([
    'id' => $id,
    'user_id' => $userId
]);

if ($stmt->rowCount() === 0) {
    header('Location: preferences.php?error=' . urlencode('Subscription not found or already inactive.'));
    exit;
}

header('Location: preferences.php?success=' . urlencode('Subscription cancelled successfully.'));
exit;
