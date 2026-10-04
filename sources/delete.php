<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$sourceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($sourceId <= 0) {
    die('Invalid source ID.');
}

$stmt = $pdo->prepare("
    SELECT id, name
    FROM sources
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sourceId]);
$source = $stmt->fetch();

if (!$source) {
    die('Source not found.');
}

try {
    /*
     * alerts.source_id has ON DELETE CASCADE in the supplied database,
     * so deleting a source also removes its related alerts.
     */
    $stmt = $pdo->prepare("DELETE FROM sources WHERE id = :id");
    $stmt->execute([':id' => $sourceId]);

    header('Location: ' . BASE_URL . '/sources/index.php');
    exit;
} catch (PDOException $e) {
    die('Unable to delete the source. Please try again.');
}
