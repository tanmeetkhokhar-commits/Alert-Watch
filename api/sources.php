<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function sourcesApiResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    requireLogin();

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $category = trim((string)($_GET['category'] ?? ''));
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    $status = strtolower(trim((string)($_GET['status'] ?? 'active')));

    if (!in_array($type, ['', 'rss', 'api'], true)) {
        sourcesApiResponse(['success' => false, 'error' => 'Invalid source type.'], 400);
    }

    if (!in_array($status, ['', 'active', 'inactive'], true)) {
        sourcesApiResponse(['success' => false, 'error' => 'Invalid source status.'], 400);
    }

    $where = [];
    $params = [];

    if ($id) {
        $where[] = 's.id = :id';
        $params['id'] = $id;
    }

    if ($category !== '') {
        $where[] = 's.category = :category';
        $params['category'] = $category;
    }

    if ($type !== '') {
        $where[] = 's.source_type = :source_type';
        $params['source_type'] = $type;
    }

    if ($status !== '') {
        $where[] = 's.status = :status';
        $params['status'] = $status;
    }

    $sql = "
        SELECT
            s.id,
            s.name,
            s.source_type,
            s.url,
            s.category,
            s.status,
            s.last_fetched,
            s.created_at,
            s.updated_at
        FROM sources s
    ";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY s.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sourcesApiResponse([
        'success' => true,
        'count' => count($sources),
        'data' => $sources
    ]);
} catch (Throwable $e) {
    sourcesApiResponse([
        'success' => false,
        'error' => 'Unable to process sources request.'
    ], 500);
}
