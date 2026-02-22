<?php
declare(strict_types=1);

final class PdoCertificatesTemplatesRepository
{
    private PDO $pdo;

    // Allowed order by columns (شاملة الحقول الجديدة)
    private const ALLOWED_ORDER_BY = [
        'id', 'code', 'name', 'language_code', 'paper_size', 'orientation',
        'is_active', 'created_at', 'logo_x', 'logo_y', 'logo_width', 'logo_height',
        'stamp_x', 'stamp_y', 'stamp_width', 'stamp_height',
        'signature_x', 'signature_y', 'signature_width', 'signature_height',
        'qr_x', 'qr_y', 'qr_width', 'qr_height',
        'table_start_x', 'table_start_y', 'table_row_height', 'table_max_rows',
        'font_family', 'font_size'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List templates with optional filters, pagination and ordering
     */
    public function all(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        // نختار الأعمدة الأساسية للعرض السريع (يمكن توسيعها حسب الحاجة)
        $sql = "SELECT id, code, name, language_code, paper_size, orientation, is_active, created_at FROM certificates_templates WHERE 1=1";
        $params = [];

        if (!empty($filters['code'])) {
            $sql .= " AND code = :code";
            $params[':code'] = $filters['code'];
        }
        if (!empty($filters['language_code'])) {
            $sql .= " AND language_code = :language_code";
            $params[':language_code'] = $filters['language_code'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM certificates_templates WHERE 1=1";
        $params = [];

        if (!empty($filters['code'])) {
            $sql .= " AND code = :code";
            $params[':code'] = $filters['code'];
        }
        if (!empty($filters['language_code'])) {
            $sql .= " AND language_code = :language_code";
            $params[':language_code'] = $filters['language_code'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates_templates WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code, ?string $languageCode = null): ?array
    {
        $sql = "SELECT * FROM certificates_templates WHERE code = :code";
        $params = [':code' => $code];
        if ($languageCode !== null) {
            $sql .= " AND language_code = :language_code";
            $params[':language_code'] = $languageCode;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Insert or update template with all fields.
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // قائمة بجميع الأعمدة في الجدول (عدا auto_increment والتواريخ التلقائية)
        $columns = [
            'code', 'name', 'language_code', 'paper_size', 'orientation',
            'html_template', 'css_style', 'is_active',
            'logo_x', 'logo_y', 'logo_width', 'logo_height',
            'stamp_x', 'stamp_y', 'stamp_width', 'stamp_height',
            'signature_x', 'signature_y', 'signature_width', 'signature_height',
            'qr_x', 'qr_y', 'qr_width', 'qr_height',
            'table_start_x', 'table_start_y', 'table_row_height', 'table_max_rows',
            'background_image', 'font_family', 'font_size'
        ];

        if ($isUpdate) {
            $id = (int)$data['id'];
            $sets = [];
            $params = [':id' => $id];
            foreach ($columns as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql = "UPDATE certificates_templates SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $insertCols = [];
        $placeholders = [];
        $params = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $insertCols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }
        // التأكد من وجود الحقول المطلوبة (code, name, language_code, html_template)
        if (!in_array('code', $insertCols)) {
            throw new InvalidArgumentException('Field "code" is required.');
        }
        if (!in_array('name', $insertCols)) {
            throw new InvalidArgumentException('Field "name" is required.');
        }
        if (!in_array('language_code', $insertCols)) {
            throw new InvalidArgumentException('Field "language_code" is required.');
        }
        if (!in_array('html_template', $insertCols)) {
            throw new InvalidArgumentException('Field "html_template" is required.');
        }

        $sql = "INSERT INTO certificates_templates (" . implode(', ', $insertCols) . ", created_at) VALUES (" . implode(', ', $placeholders) . ", NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM certificates_templates WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}