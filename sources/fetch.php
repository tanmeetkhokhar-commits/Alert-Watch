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
    SELECT
        id,
        name,
        source_type,
        url,
        category,
        status
    FROM sources
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sourceId]);
$source = $stmt->fetch();

if (!$source) {
    die('Source not found.');
}

if ($source['status'] !== 'active') {
    die('This source is inactive.');
}

$url = trim($source['url']);

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    die('Source URL is invalid.');
}

function fetchRemoteContent(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled.');
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'AlertWatch/1.0 Emergency Alert Aggregator',
        CURLOPT_HTTPHEADER => [
            'Accept: application/rss+xml, application/xml, application/json, text/xml, */*'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $content = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($content === false || $content === '') {
        throw new RuntimeException(
            $curlError !== '' ? 'Unable to retrieve source: ' . $curlError : 'Source returned no data.'
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Source returned HTTP status ' . $httpCode . '.');
    }

    return $content;
}

function cleanText($value): string
{
    if ($value === null) {
        return '';
    }

    $value = is_scalar($value) ? (string) $value : '';

    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function inferSeverity(string $title, string $description): string
{
    $text = strtolower($title . ' ' . $description);

    if (preg_match('/\b(critical|life threatening|life-threatening|evacuate|evacuation|emergency)\b/i', $text)) {
        return 'critical';
    }

    if (preg_match('/\b(severe|extreme|danger|dangerous|major|urgent)\b/i', $text)) {
        return 'high';
    }

    if (preg_match('/\b(warning|watch|moderate|significant)\b/i', $text)) {
        return 'medium';
    }

    return 'low';
}

function parsePublishedDate(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function getRssField(SimpleXMLElement $item, array $names): string
{
    foreach ($names as $name) {
        if (isset($item->{$name})) {
            $value = cleanText((string) $item->{$name});
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function getRssNamespaceField(SimpleXMLElement $item, string $namespace, string $field): string
{
    $namespaces = $item->getNameSpaces(true);

    if (!isset($namespaces[$namespace])) {
        return '';
    }

    $children = $item->children($namespaces[$namespace]);

    if (isset($children->{$field})) {
        return cleanText((string) $children->{$field});
    }

    return '';
}

function extractRssItems(SimpleXMLElement $xml): array
{
    $items = [];

    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $items[] = $item;
        }
        return $items;
    }

    if (isset($xml->entry)) {
        foreach ($xml->entry as $item) {
            $items[] = $item;
        }
    }

    return $items;
}

function rssItemData(SimpleXMLElement $item, string $defaultCategory): array
{
    $title = getRssField($item, ['title']);
    $description = getRssField($item, ['description', 'summary', 'content']);

    $externalId = getRssField($item, ['guid', 'id']);

    $sourceUrl = getRssField($item, ['link']);

    if ($sourceUrl === '') {
        $namespaces = $item->getNameSpaces(true);

        if (isset($namespaces['atom'])) {
            $atom = $item->children($namespaces['atom']);

            if (isset($atom->link)) {
                foreach ($atom->link as $link) {
                    $attributes = $link->attributes();

                    if (isset($attributes['href'])) {
                        $sourceUrl = trim((string) $attributes['href']);
                        if ($sourceUrl !== '') {
                            break;
                        }
                    }
                }
            }
        }
    }

    $published = getRssField($item, [
        'pubDate',
        'published',
        'updated',
        'date'
    ]);

    $category = getRssField($item, ['category']);

    $location = getRssField($item, ['location', 'where', 'area']);

    if ($location === '') {
        $location = getRssNamespaceField($item, 'georss', 'where');
    }

    if ($category === '') {
        $category = $defaultCategory;
    }

    if ($externalId === '' && $sourceUrl !== '') {
        $externalId = $sourceUrl;
    }

    if ($externalId === '') {
        $externalId = sha1($title . '|' . $published . '|' . $sourceUrl);
    }

    return [
        'external_id' => $externalId,
        'title' => $title,
        'description' => $description,
        'category' => $category !== '' ? $category : null,
        'location' => $location !== '' ? $location : null,
        'severity' => inferSeverity($title, $description),
        'published_at' => parsePublishedDate($published),
        'source_url' => $sourceUrl !== '' ? $sourceUrl : null
    ];
}

function extractApiRecords($decoded): array
{
    if (!is_array($decoded)) {
        return [];
    }

    /*
     * Support common API response structures:
     * [ ... ]
     * { "alerts": [ ... ] }
     * { "data": [ ... ] }
     * { "items": [ ... ] }
     * { "results": [ ... ] }
     */
    foreach (['alerts', 'data', 'items', 'results', 'features'] as $key) {
        if (isset($decoded[$key]) && is_array($decoded[$key])) {
            return $decoded[$key];
        }
    }

    /*
     * A single alert object is also accepted.
     */
    if (
        isset($decoded['title']) ||
        isset($decoded['name']) ||
        isset($decoded['headline'])
    ) {
        return [$decoded];
    }

    return [];
}

function apiRecordData(array $record, string $defaultCategory): array
{
    /*
     * GeoJSON-style records may store the alert properties inside "properties".
     */
    if (isset($record['properties']) && is_array($record['properties'])) {
        $properties = $record['properties'];

        if (isset($record['id']) && !isset($properties['id'])) {
            $properties['id'] = $record['id'];
        }

        if (isset($record['geometry']) && !isset($properties['geometry'])) {
            $properties['geometry'] = $record['geometry'];
        }

        $record = $properties;
    }

    $externalId = cleanText(
        $record['id']
        ?? $record['external_id']
        ?? $record['identifier']
        ?? $record['guid']
        ?? ''
    );

    $title = cleanText(
        $record['title']
        ?? $record['headline']
        ?? $record['name']
        ?? ''
    );

    $description = cleanText(
        $record['description']
        ?? $record['summary']
        ?? $record['details']
        ?? $record['text']
        ?? ''
    );

    $category = cleanText(
        $record['category']
        ?? $record['event']
        ?? $record['type']
        ?? ''
    );

    $location = cleanText(
        $record['location']
        ?? $record['area']
        ?? $record['place']
        ?? $record['region']
        ?? ''
    );

    $published = cleanText(
        $record['published_at']
        ?? $record['published']
        ?? $record['pubDate']
        ?? $record['sent']
        ?? $record['updated']
        ?? $record['date']
        ?? ''
    );

    $sourceUrl = cleanText(
        $record['source_url']
        ?? $record['url']
        ?? $record['link']
        ?? ''
    );

    $severity = strtolower(cleanText(
        $record['severity']
        ?? $record['severity_level']
        ?? ''
    ));

    if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
        $severity = inferSeverity($title, $description);
    }

    if ($category === '') {
        $category = $defaultCategory;
    }

    if ($externalId === '') {
        $externalId = $sourceUrl !== ''
            ? $sourceUrl
            : sha1($title . '|' . $published . '|' . $location);
    }

    return [
        'external_id' => $externalId,
        'title' => $title,
        'description' => $description,
        'category' => $category !== '' ? $category : null,
        'location' => $location !== '' ? $location : null,
        'severity' => $severity,
        'published_at' => parsePublishedDate($published),
        'source_url' => $sourceUrl !== '' ? $sourceUrl : null
    ];
}

try {
    $content = fetchRemoteContent($url);

    $newAlerts = 0;
    $skippedAlerts = 0;

    $pdo->beginTransaction();

    if ($source['source_type'] === 'rss') {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            libxml_clear_errors();
            throw new RuntimeException('The source did not return valid RSS/XML data.');
        }

        $items = extractRssItems($xml);

        foreach ($items as $item) {
            $alert = rssItemData($item, (string) ($source['category'] ?? ''));

            if ($alert['title'] === '') {
                $skippedAlerts++;
                continue;
            }

            $check = $pdo->prepare("
                SELECT id
                FROM alerts
                WHERE source_id = :source_id
                  AND external_id = :external_id
                LIMIT 1
            ");

            $check->execute([
                ':source_id' => $sourceId,
                ':external_id' => $alert['external_id']
            ]);

            if ($check->fetch()) {
                $skippedAlerts++;
                continue;
            }

            $insert = $pdo->prepare("
                INSERT INTO alerts
                (
                    source_id,
                    external_id,
                    title,
                    description,
                    category,
                    location,
                    severity,
                    published_at,
                    source_url,
                    status
                )
                VALUES
                (
                    :source_id,
                    :external_id,
                    :title,
                    :description,
                    :category,
                    :location,
                    :severity,
                    :published_at,
                    :source_url,
                    'active'
                )
            ");

            try {
                $insert->execute([
                    ':source_id' => $sourceId,
                    ':external_id' => $alert['external_id'],
                    ':title' => $alert['title'],
                    ':description' => $alert['description'],
                    ':category' => $alert['category'],
                    ':location' => $alert['location'],
                    ':severity' => $alert['severity'],
                    ':published_at' => $alert['published_at'],
                    ':source_url' => $alert['source_url']
                ]);

                $newAlerts++;
            } catch (PDOException $e) {
                // A duplicate can still occur if the same external ID appears
                // more than once in a feed or another fetch runs concurrently.
                if ($e->getCode() === '23000') {
                    $skippedAlerts++;
                    continue;
                }

                throw $e;
            }
        }

        libxml_clear_errors();

    } elseif ($source['source_type'] === 'api') {
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('The API did not return valid JSON data.');
        }

        $records = extractApiRecords($decoded);

        foreach ($records as $record) {
            if (!is_array($record)) {
                $skippedAlerts++;
                continue;
            }

            $alert = apiRecordData($record, (string) ($source['category'] ?? ''));

            if ($alert['title'] === '') {
                $skippedAlerts++;
                continue;
            }

            $check = $pdo->prepare("
                SELECT id
                FROM alerts
                WHERE source_id = :source_id
                  AND external_id = :external_id
                LIMIT 1
            ");

            $check->execute([
                ':source_id' => $sourceId,
                ':external_id' => $alert['external_id']
            ]);

            if ($check->fetch()) {
                $skippedAlerts++;
                continue;
            }

            $insert = $pdo->prepare("
                INSERT INTO alerts
                (
                    source_id,
                    external_id,
                    title,
                    description,
                    category,
                    location,
                    severity,
                    published_at,
                    source_url,
                    status
                )
                VALUES
                (
                    :source_id,
                    :external_id,
                    :title,
                    :description,
                    :category,
                    :location,
                    :severity,
                    :published_at,
                    :source_url,
                    'active'
                )
            ");

            try {
                $insert->execute([
                    ':source_id' => $sourceId,
                    ':external_id' => $alert['external_id'],
                    ':title' => $alert['title'],
                    ':description' => $alert['description'],
                    ':category' => $alert['category'],
                    ':location' => $alert['location'],
                    ':severity' => $alert['severity'],
                    ':published_at' => $alert['published_at'],
                    ':source_url' => $alert['source_url']
                ]);

                $newAlerts++;
            } catch (PDOException $e) {
                // A duplicate can still occur if the same external ID appears
                // more than once in a feed or another fetch runs concurrently.
                if ($e->getCode() === '23000') {
                    $skippedAlerts++;
                    continue;
                }

                throw $e;
            }
        }
    } else {
        throw new RuntimeException('Unsupported source type.');
    }

    $update = $pdo->prepare("
        UPDATE sources
        SET last_fetched = NOW()
        WHERE id = :id
    ");
    $update->execute([':id' => $sourceId]);

    $pdo->commit();

    $message = sprintf(
        'Fetch completed. New alerts: %d. Existing/skipped: %d.',
        $newAlerts,
        $skippedAlerts
    );

    /*
     * Redirect with a short status message so the user can verify the
     * result on the Sources page without changing the existing UI structure.
     */
    header(
        'Location: ' . BASE_URL .
        '/sources/index.php?message=' . rawurlencode($message)
    );
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = 'Fetch failed: ' . $e->getMessage();

    header(
        'Location: ' . BASE_URL .
        '/sources/index.php?error=' . rawurlencode($message)
    );
    exit;
}
