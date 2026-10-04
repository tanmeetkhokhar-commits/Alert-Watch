<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function apiResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiParam(string $name, string $default = ''): string
{
    return trim((string)($_GET[$name] ?? $default));
}

try {
    requireLogin();

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $q = apiParam('q');
    $category = apiParam('category');
    $location = apiParam('location');
    $severity = strtolower(apiParam('severity'));
    $status = strtolower(apiParam('status'));
    $sourceId = filter_input(INPUT_GET, 'source_id', FILTER_VALIDATE_INT);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
    $limit = ($limit && $limit > 0) ? min($limit, 100) : 50;

    $allowedSeverity = ['low', 'medium', 'high', 'critical'];
    $allowedStatus = ['active', 'archived'];

    if ($severity !== '' && !in_array($severity, $allowedSeverity, true)) {
        apiResponse(['success' => false, 'error' => 'Invalid severity.'], 400);
    }

    if ($status !== '' && !in_array($status, $allowedStatus, true)) {
        apiResponse(['success' => false, 'error' => 'Invalid status.'], 400);
    }

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT
                a.id, a.source_id, a.external_id, a.title, a.description,
                a.category, a.location, a.severity, a.published_at,
                a.source_url, a.status, a.created_at, a.updated_at,
                s.name AS source_name, s.source_type
            FROM alerts a
            LEFT JOIN sources s ON s.id = a.source_id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            apiResponse(['success' => false, 'error' => 'Alert not found.'], 404);
        }

        apiResponse([
            'success' => true,
            'data' => $alert
        ]);
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(a.title LIKE :q OR a.description LIKE :q OR a.category LIKE :q OR a.location LIKE :q OR s.name LIKE :q)";
        $params['q'] = '%' . $q . '%';
    }

    if ($category !== '') {
        $where[] = 'a.category = :category';
        $params['category'] = $category;
    }

    if ($location !== '') {
        $where[] = 'a.location LIKE :location';
        $params['location'] = '%' . $location . '%';
    }

    if ($severity !== '') {
        $where[] = 'a.severity = :severity';
        $params['severity'] = $severity;
    }

    if ($status !== '') {
        $where[] = 'a.status = :status';
        $params['status'] = $status;
    } else {
        $where[] = "a.status = 'active'";
    }

    if ($sourceId) {
        $where[] = 'a.source_id = :source_id';
        $params['source_id'] = $sourceId;
    }

    $sql = "
        SELECT
            a.id, a.source_id, a.external_id, a.title, a.description,
            a.category, a.location, a.severity, a.published_at,
            a.source_url, a.status, a.created_at, a.updated_at,
            s.name AS source_name, s.source_type
        FROM alerts a
        LEFT JOIN sources s ON s.id = a.source_id
    ";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiResponse([
        'success' => true,
        'count' => count($alerts),
        'data' => $alerts
    ]);
} catch (Throwable $e) {
    apiResponse([
        'success' => false,
        'error' => 'Unable to process alerts request.'
    ], 500);
}
