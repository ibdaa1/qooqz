<?php
declare(strict_types=1);

final class CertificatesProductsTranslationsRepository
{
    private PDO $pdo;

    // الحقول الوحيدة المسموح بها في الجدول — brand محذوف نهائياً
    private const ALLOWED_FIELDS = ['product_id', 'language_code', 'name'];

    private const ALLOWED_ORDER_BY = ['id', 'product_id', 'language_code', 'name'];

    private const FILTERABLE_COLUMNS = ['product_id', 'language_code', 'name'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // Strip unknown/deleted columns from data
    // يضمن عدم وصول brand أو أي حقل غير موجود للـ SQL
    // ================================
    private function sanitize(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));
    }

    // ================================
    // List
    // ================================
    public function all(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql    = "SELECT id, product_id, language_code, name FROM certificates_products_translations WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'name') {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
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
        $sql    = "SELECT COUNT(*) FROM certificates_products_translations WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'name') {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
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
            "SELECT id, product_id, language_code, name
             FROM certificates_products_translations
             WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Save — upsert on (product_id, language_code)
    // sanitize() يضمن عدم وصول أي حقل غير موجود في الجدول
    // ================================
    public function save(array $data): int
    {
        $clean    = $this->sanitize($data);          // يحذف brand وأي حقل آخر
        $isUpdate = !empty($data['id']);

        $params = [
            ':product_id'    => $clean['product_id'],
            ':language_code' => $clean['language_code'],
            ':name'          => $clean['name'],
        ];

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
            $stmt = $this->pdo->prepare("
                UPDATE certificates_products_translations
                SET product_id    = :product_id,
                    language_code = :language_code,
                    name          = :name
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        // Upsert: على تعارض (product_id, language_code) يُحدّث name فقط
        $stmt = $this->pdo->prepare("
            INSERT INTO certificates_products_translations (product_id, language_code, name)
            VALUES (:product_id, :language_code, :name)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmt->execute($params);

        $lastId = (int)$this->pdo->lastInsertId();

        // ON DUPLICATE KEY UPDATE يُرجع 0 إذا لم يُدرج سجل جديد
        if ($lastId === 0) {
            $sel = $this->pdo->prepare(
                "SELECT id FROM certificates_products_translations
                 WHERE product_id = :product_id AND language_code = :language_code
                 LIMIT 1"
            );
            $sel->execute([
                ':product_id'    => $params[':product_id'],
                ':language_code' => $params[':language_code'],
            ]);
            $lastId = (int)$sel->fetchColumn();
        }

        return $lastId;
    }

    // ================================
    // Delete by ID
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM certificates_products_translations WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete by product_id + language_code
    // ================================
    public function deleteByProductAndLang(int $productId, string $languageCode): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM certificates_products_translations
             WHERE product_id = :product_id AND language_code = :language_code"
        );
        return $stmt->execute([
            ':product_id'    => $productId,
            ':language_code' => $languageCode,
        ]);
    }
}