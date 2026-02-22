<?php
declare(strict_types=1);

final class PdoCertificatesIssuedRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER = [
        'id',
        'version_id',
        'certificate_number',
        'issued_at',
        'printable_until',
        'verification_code',
        'issued_by',
        'language_code',
        'is_cancelled',
        'created_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * جلب قائمة الإصدارات مع فلترة حسب tenant
     */
    public function all(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $sql = "
            SELECT i.*
            FROM certificates_issued i
            INNER JOIN certificates_versions v ON i.version_id = v.id
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        // تطبيق الفلاتر (أسماء الأعمدة في جدول issued)
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            // نسمح فقط بالأعمدة الموجودة في جدول issued
            if (in_array($key, self::ALLOWED_ORDER, true)) {
                $sql .= " AND i.$key = :$key";
                $params[":$key"] = $value;
            }
        }

        // التحقق من صحة orderBy
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY i.$orderBy $orderDir";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
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

    /**
     * عدد السجلات بعد تطبيق الفلاتر
     */
    public function count(int $tenantId, array $filters): int
    {
        $sql = "
            SELECT COUNT(i.id)
            FROM certificates_issued i
            INNER JOIN certificates_versions v ON i.version_id = v.id
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($key, self::ALLOWED_ORDER, true)) {
                $sql .= " AND i.$key = :$key";
                $params[":$key"] = $value;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * العثور على سجل واحد بواسطة ID مع التحقق من tenant
     */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*
            FROM certificates_issued i
            INNER JOIN certificates_versions v ON i.version_id = v.id
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND i.id = :id
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id'        => $id
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * حفظ (إدراج أو تحديث) سجل
     */
    public function save(int $tenantId, array $data): int
    {
        // إذا وجد id نقوم بالتحديث
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);

            // بناء جملة SET
            $sets = [];
            foreach (array_keys($data) as $col) {
                $sets[] = "$col = :$col";
            }
            $sql = "UPDATE certificates_issued SET " . implode(', ', $sets) . " WHERE id = :id";
            $data['id'] = $id;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $id;
        }

        // إدراج جديد
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO certificates_issued ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * حذف سجل (مع التأكد من أنه يتبع tenant معين)
     */
    public function delete(int $tenantId, int $id): bool
    {
        // نحذف باستخدام JOIN للتأكد من tenant
        $stmt = $this->pdo->prepare("
            DELETE i
            FROM certificates_issued i
            INNER JOIN certificates_versions v ON i.version_id = v.id
            INNER JOIN certificates_requests r ON v.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND i.id = :id
        ");
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id'        => $id
        ]);
    }
}