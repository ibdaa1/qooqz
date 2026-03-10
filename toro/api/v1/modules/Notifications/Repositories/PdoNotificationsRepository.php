<?php
/**
 * TORO — v1/modules/Notifications/Repositories/PdoNotificationsRepository.php
 */
declare(strict_types=1);

final class PdoNotificationsRepository implements NotificationsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── Templates ──────────────────────────────────────────────
    public function findAllTemplates(array $filters = []): array
    {
        $channel  = $filters['channel']   ?? null;
        $isActive = $filters['is_active'] ?? null;
        $limit    = max(1, min((int)($filters['limit']  ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM notification_templates WHERE 1=1';
        $params = [];

        if ($channel !== null) {
            $sql .= ' AND channel = :channel';
            $params[':channel'] = $channel;
        }
        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }

        $sql .= ' ORDER BY id ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countTemplates(array $filters = []): int
    {
        $channel  = $filters['channel']   ?? null;
        $isActive = $filters['is_active'] ?? null;

        $sql    = 'SELECT COUNT(*) FROM notification_templates WHERE 1=1';
        $params = [];

        if ($channel !== null) {
            $sql .= ' AND channel = :channel';
            $params[':channel'] = $channel;
        }
        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findTemplateById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notification_templates WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findTemplateBySlugChannel(string $slug, string $channel): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_templates WHERE slug = :slug AND channel = :channel LIMIT 1'
        );
        $stmt->execute([':slug' => $slug, ':channel' => $channel]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createTemplate(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notification_templates (slug, channel, is_active)
             VALUES (:slug, :channel, :is_active)'
        );
        $stmt->execute([
            ':slug'      => $data['slug'],
            ':channel'   => $data['channel'],
            ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTemplate(int $id, array $data): bool
    {
        $sets   = [];
        $params = [':id' => $id];

        if (array_key_exists('slug', $data))      { $sets[] = 'slug = :slug';           $params[':slug']      = $data['slug']; }
        if (array_key_exists('channel', $data))   { $sets[] = 'channel = :channel';     $params[':channel']   = $data['channel']; }
        if (array_key_exists('is_active', $data)) { $sets[] = 'is_active = :is_active'; $params[':is_active'] = (int)$data['is_active']; }

        if (empty($sets)) return false;

        $stmt = $this->pdo->prepare('UPDATE notification_templates SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteTemplate(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM notification_templates WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Template Translations ──────────────────────────────────
    public function getTemplateTranslations(int $templateId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_template_translations WHERE template_id = :template_id'
        );
        $stmt->execute([':template_id' => $templateId]);
        return $stmt->fetchAll();
    }

    public function upsertTemplateTranslation(int $templateId, int $languageId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notification_template_translations (template_id, language_id, subject, body)
             VALUES (:template_id, :language_id, :subject, :body)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body)'
        );
        $stmt->execute([
            ':template_id' => $templateId,
            ':language_id' => $languageId,
            ':subject'     => $data['subject'] ?? null,
            ':body'        => $data['body'],
        ]);
        return (int)$this->pdo->lastInsertId() ?: $templateId;
    }

    // ── Notifications Log ──────────────────────────────────────
    public function findAllLogs(array $filters = []): array
    {
        $userId  = $filters['user_id']  ?? null;
        $channel = $filters['channel']  ?? null;
        $status  = $filters['status']   ?? null;
        $limit   = max(1, min((int)($filters['limit']  ?? 50), 200));
        $offset  = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM notifications_log WHERE 1=1';
        $params = [];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }
        if ($channel !== null) {
            $sql .= ' AND channel = :channel';
            $params[':channel'] = $channel;
        }
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countLogs(array $filters = []): int
    {
        $userId  = $filters['user_id']  ?? null;
        $channel = $filters['channel']  ?? null;
        $status  = $filters['status']   ?? null;

        $sql    = 'SELECT COUNT(*) FROM notifications_log WHERE 1=1';
        $params = [];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }
        if ($channel !== null) {
            $sql .= ' AND channel = :channel';
            $params[':channel'] = $channel;
        }
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function createLog(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications_log (user_id, template_id, channel, recipient, status)
             VALUES (:user_id, :template_id, :channel, :recipient, :status)'
        );
        $stmt->execute([
            ':user_id'     => $data['user_id']     ?? null,
            ':template_id' => $data['template_id'] ?? null,
            ':channel'     => $data['channel'],
            ':recipient'   => $data['recipient'],
            ':status'      => $data['status']      ?? 'pending',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findLogById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications_log WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateLogStatus(int $id, string $status, ?string $sentAt): bool
    {
        $sql    = 'UPDATE notifications_log SET status = :status';
        $params = [':status' => $status, ':id' => $id];

        if ($sentAt !== null) {
            $sql .= ', sent_at = :sent_at';
            $params[':sent_at'] = $sentAt;
        }

        $sql .= ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
