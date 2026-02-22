<?php
declare(strict_types=1);

final class PdoCertificatesVersionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER = [
        'id','request_id','version_number',
        'is_active','created_at','approved_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {

        $sql = "
            SELECT v.*
            FROM certificates_versions v
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;
            $sql .= " AND v.$key = :$key";
            $params[":$key"] = $value;
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER, true)
            ? $orderBy : 'id';

        $orderDir = strtoupper($orderDir) === 'ASC'
            ? 'ASC' : 'DESC';

        $sql .= " ORDER BY v.$orderBy $orderDir";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        if ($limit !== null)
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if ($offset !== null)
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters): int
    {
        $sql = "
            SELECT COUNT(v.id)
            FROM certificates_versions v
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;
            $sql .= " AND v.$key = :$key";
            $params[":$key"] = $value;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*
            FROM certificates_versions v
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND v.id = :id
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            foreach ($data as $key => $val) {
                $sets[] = "$key = :$key";
            }

            $sql = "UPDATE certificates_versions SET "
                . implode(',', $sets)
                . " WHERE id = :id";

            $data['id'] = $id;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);

            return $id;
        }

        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(',:', array_keys($data));

        $sql = "
            INSERT INTO certificates_versions
            ($columns)
            VALUES
            ($placeholders)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE v FROM certificates_versions v
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND v.id = :id
        ");

        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $id
        ]);
    }
}