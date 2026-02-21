<?php
declare(strict_types=1);

final class PdoNotificationsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'user_id', 'entity_id', 'is_read', 'sent_at', 'notification_type_id'
    ];

    private const FILTERABLE_COLUMNS = [
        'user_id', 'entity_id', 'is_read', 'notification_type_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get list of notifications with optional filters, ordering and pagination.
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sent_at',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT * FROM notifications WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        // Date range for sent_at
        if (!empty($filters['date_from'])) {
            $sql .= " AND sent_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND sent_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sent_at';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderBy $orderDir";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND sent_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND sent_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            $params = [':id' => $id];
            $allowed = ['user_id', 'entity_id', 'title', 'message', 'is_read', 'data', 'notification_type_id'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }
            // We don't update sent_at intentionally

            $sql = "UPDATE notifications SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $cols = [];
        $placeholders = [];
        $params = [];
        $allowed = ['user_id', 'entity_id', 'title', 'message', 'is_read', 'data', 'notification_type_id'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }
        // Ensure required fields: user_id, title, message are NOT NULL
        if (!in_array('user_id', $cols)) {
            throw new InvalidArgumentException('Field "user_id" is required.');
        }
        if (!in_array('title', $cols)) {
            throw new InvalidArgumentException('Field "title" is required.');
        }
        if (!in_array('message', $cols)) {
            throw new InvalidArgumentException('Field "message" is required.');
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $cols) . ", sent_at) VALUES (" . implode(', ', $placeholders) . ", NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get unread count for a user.
     */
    public function countUnreadByUser(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}