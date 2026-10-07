<?php

final class AlertWatchRssProcessor
{
    public function __construct(private PDO $pdo)
    {
    }

  
    public function process(int $sourceId, array $records): array
    {
        if ($sourceId < 1) {
            throw new InvalidArgumentException('Invalid source ID.');
        }

        $sourceStmt = $this->pdo->prepare("
            SELECT id, category, url, status
            FROM sources
            WHERE id = :id
            LIMIT 1
        ");
        $sourceStmt->execute(['id' => $sourceId]);
        $source = $sourceStmt->fetch();

        if (!$source) {
            throw new RuntimeException('Source not found.');
        }

        $inserted = 0;
        $duplicates = 0;
        $failed = 0;

        $duplicateStmt = $this->pdo->prepare("
            SELECT id
            FROM alerts
            WHERE source_id = :source_id
              AND external_id = :external_id
            LIMIT 1
        ");

        $insertStmt = $this->pdo->prepare("
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
                status,
                created_at,
                updated_at
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
                'active',
                NOW(),
                NOW()
            )
        ");

        $logStmt = $this->pdo->prepare("
            INSERT INTO alert_logs
            (alert_id, action, details, created_at)
            VALUES
            (:alert_id, :action, :details, NOW())
        ");

        $this->pdo->beginTransaction();

        try {
            foreach ($records as $record) {
                $externalId = trim((string)($record['external_id'] ?? ''));
                $title = trim((string)($record['title'] ?? ''));

                if ($externalId === '' || $title === '') {
                    $failed++;
                    continue;
                }

                $duplicateStmt->execute([
                    'source_id' => $sourceId,
                    'external_id' => $externalId,
                ]);

                if ($duplicateStmt->fetch()) {
                    $duplicates++;
                    continue;
                }

                try {
                    $insertStmt->execute([
                        'source_id' => $sourceId,
                        'external_id' => mb_substr($externalId, 0, 255),
                        'title' => mb_substr($title, 0, 500),
                        'description' => $record['description'] ?? null,
                        'category' => $record['category'] ?? $source['category'],
                        'location' => $record['location'] ?? null,
                        'severity' => in_array(($record['severity'] ?? 'low'), ['low','medium','high','critical'], true)
                            ? $record['severity']
                            : 'low',
                        'published_at' => $record['published_at'] ?? null,
                        'source_url' => $record['source_url'] ?? null,
                    ]);

                    $alertId = (int)$this->pdo->lastInsertId();

                    $logStmt->execute([
                        'alert_id' => $alertId,
                        'action' => 'rss_imported',
                        'details' => 'Imported from RSS source ID ' . $sourceId . '.',
                    ]);

                    $inserted++;
                } catch (PDOException $e) {
                    // The unique key is a second line of duplicate protection.
                    if ((int)$e->errorInfo[1] === 1062) {
                        $duplicates++;
                        continue;
                    }

                    throw $e;
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return [
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'failed' => $failed,
        ];
    }
}
