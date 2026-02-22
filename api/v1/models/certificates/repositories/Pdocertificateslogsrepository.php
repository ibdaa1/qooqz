<?php
declare(strict_types=1);

final class CertificatesLogsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'request_id', 'user_id', 'action_type', 'created_at'
    ];

    private const VALID_ACTION_TYPES = [
        'create', 'update', 'approve', 'audit', 'payment_sent', 'issue', 'reject'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters + pagination
    // ================================
    public function all(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql    = "SELECT cl.* FROM certificates_logs cl WHERE 1=1";
        $params = [];

        if (!empty($filters['request_id'])) {
            $sql .= " AND cl.request_id = :request_id";
            $params[':request_id'] = (int)$filters['request_id'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND cl.user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['action_type']) && in_array($filters['action_type'], self::VALID_ACTION_TYPES, true)) {
            $sql .= " AND cl.action_type = :action_type";
            $params[':action_type'] = $filters['action_type'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND cl.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND cl.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY cl.{$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null)  $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM certificates_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['request_id'])) {
            $sql .= " AND request_id = :request_id";
            $params[':request_id'] = (int)$filters['request_id'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['action_type']) && in_array($filters['action_type'], self::VALID_ACTION_TYPES, true)) {
            $sql .= " AND action_type = :action_type";
            $params[':action_type'] = $filters['action_type'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM certificates_logs WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Insert log entry (no update/delete — logs are immutable)
    // ================================
    public function insert(int $requestId, int $userId, string $actionType, ?string $notes = null): int
    {
        if (!in_array($actionType, self::VALID_ACTION_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid action_type '{$actionType}'. Allowed: " . implode(', ', self::VALID_ACTION_TYPES)
            );
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO certificates_logs (request_id, user_id, action_type, notes)
            VALUES (:request_id, :user_id, :action_type, :notes)
        ");
        $stmt->execute([
            ':request_id'  => $requestId,
            ':user_id'     => $userId,
            ':action_type' => $actionType,
            ':notes'       => $notes,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public static function validActionTypes(): array
    {
        return self::VALID_ACTION_TYPES;
    }
}