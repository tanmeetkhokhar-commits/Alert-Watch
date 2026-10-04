<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function subscriptionsApiResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function subscriptionsJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        subscriptionsApiResponse(['success' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    return $data;
}

try {
    requireLogin();
    $userId = currentUserId();

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            $stmt = $pdo->prepare("
                SELECT
                    id, user_id, category, location, severity,
                    notification_method, status, created_at, updated_at
                FROM subscriptions
                WHERE id = :id AND user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                subscriptionsApiResponse(['success' => false, 'error' => 'Subscription not found.'], 404);
            }

            subscriptionsApiResponse([
                'success' => true,
                'data' => $subscription
            ]);
        }

        $stmt = $pdo->prepare("
            SELECT
                id, user_id, category, location, severity,
                notification_method, status, created_at, updated_at
            FROM subscriptions
            WHERE user_id = :user_id
            ORDER BY id DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        subscriptionsApiResponse([
            'success' => true,
            'count' => count($subscriptions),
            'data' => $subscriptions
        ]);
    }

    if ($method === 'POST') {
        $data = subscriptionsJsonBody();

        $category = trim((string)($data['category'] ?? ''));
        $location = trim((string)($data['location'] ?? ''));
        $severity = strtolower(trim((string)($data['severity'] ?? '')));
        $notificationMethod = strtolower(trim((string)($data['notification_method'] ?? 'dashboard')));

        $allowedSeverity = ['', 'low', 'medium', 'high', 'critical'];
        $allowedMethods = ['dashboard', 'email'];

        if (!in_array($severity, $allowedSeverity, true)) {
            subscriptionsApiResponse(['success' => false, 'error' => 'Invalid severity.'], 400);
        }

        if (!in_array($notificationMethod, $allowedMethods, true)) {
            subscriptionsApiResponse(['success' => false, 'error' => 'Invalid notification method.'], 400);
        }

        if (mb_strlen($category) > 100 || mb_strlen($location) > 255) {
            subscriptionsApiResponse(['success' => false, 'error' => 'Category or location is too long.'], 400);
        }

        $check = $pdo->prepare("
            SELECT id
            FROM subscriptions
            WHERE user_id = :user_id
              AND COALESCE(category, '') = :category
              AND COALESCE(location, '') = :location
              AND COALESCE(severity, '') = :severity
              AND status = 'active'
            LIMIT 1
        ");
        $check->execute([
            'user_id' => $userId,
            'category' => $category,
            'location' => $location,
            'severity' => $severity
        ]);

        $existing = $check->fetch(PDO::FETCH_ASSOC);

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

            subscriptionsApiResponse([
                'success' => true,
                'message' => 'Existing subscription updated.',
                'id' => (int)$existing['id']
            ]);
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

        subscriptionsApiResponse([
            'success' => true,
            'message' => 'Subscription created successfully.',
            'id' => (int)$pdo->lastInsertId()
        ], 201);
    }

    if ($method === 'DELETE') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $body = subscriptionsJsonBody();
            $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        }

        if (!$id) {
            subscriptionsApiResponse(['success' => false, 'error' => 'Subscription id is required.'], 400);
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
            subscriptionsApiResponse(['success' => false, 'error' => 'Subscription not found or already inactive.'], 404);
        }

        subscriptionsApiResponse([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
            'id' => (int)$id
        ]);
    }

    subscriptionsApiResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    subscriptionsApiResponse([
        'success' => false,
        'error' => 'Unable to process subscription request.'
    ], 500);
}
