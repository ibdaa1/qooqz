<?php
/**
 * TORO — v1/modules/AuditLogs/Repositories/PdoAuditLogsRepository.php
 */
declare(strict_types=1);

final class PdoAuditLogsRepository implements AuditLogsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(array $filters = []): array
    {
        $userId   = $filters['user_id'] ?? null;
        $entity   = $filters['entity']  ?? null;
        $action   = $filters['action']  ?? null;
        $limit    = max(1, min((int)($filters['limit']  ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM audit_logs WHERE 1=1';
        $params = [];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }
        if ($entity !== null) {
            $sql .= ' AND entity = :entity';
            $params[':entity'] = $entity;
        }
        if ($action !== null) {
            $sql .= ' AND action = :action';
            $params[':action'] = $action;
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

    public function countAll(array $filters = []): int
    {
        $userId = $filters['user_id'] ?? null;
        $entity = $filters['entity']  ?? null;
        $action = $filters['action']  ?? null;

        $sql    = 'SELECT COUNT(*) FROM audit_logs WHERE 1=1';
        $params = [];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }
        if ($entity !== null) {
            $sql .= ' AND entity = :entity';
            $params[':entity'] = $entity;
        }
        if ($action !== null) {
            $sql .= ' AND action = :action';
            $params[':action'] = $action;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs
             (user_id, action, entity, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES
             (:user_id, :action, :entity, :entity_id, :old_values, :new_values, :ip_address, :user_agent)'
        );
        $stmt->execute([
            ':user_id'    => $data['user_id']    ?? null,
            ':action'     => $data['action'],
            ':entity'     => $data['entity']     ?? null,
            ':entity_id'  => $data['entity_id']  ?? null,
            ':old_values' => isset($data['old_values']) ? json_encode($data['old_values']) : null,
            ':new_values' => isset($data['new_values']) ? json_encode($data['new_values']) : null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
