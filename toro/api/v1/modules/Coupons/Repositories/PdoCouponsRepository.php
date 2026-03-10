<?php
/**
 * TORO — v1/modules/Coupons/Repositories/PdoCouponsRepository.php
 */
declare(strict_types=1);

final class PdoCouponsRepository implements CouponsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(array $filters = []): array
    {
        $lang     = $filters['lang']      ?? null;
        $isActive = $filters['is_active'] ?? null;
        $limit    = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));
        $langId   = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                c.id, c.code, c.type, c.value, c.min_order_amount,
                c.max_uses, c.uses_count, c.starts_at, c.expires_at,
                c.is_active, c.created_at,
                ct.description,
                l.code AS lang_code
            FROM coupons c
            LEFT JOIN coupon_translations ct
                ON ct.coupon_id = c.id
                AND ct.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = ct.language_id
            WHERE 1=1
        ";

        $params = [':lang_id' => $langId];

        if ($isActive !== null) {
            $sql .= ' AND c.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $sql .= ' ORDER BY c.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_null($val) ? \PDO::PARAM_NULL : (is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $isActive = $filters['is_active'] ?? null;
        $sql    = 'SELECT COUNT(*) FROM coupons WHERE 1=1';
        $params = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.code, c.type, c.value, c.min_order_amount,
                   c.max_uses, c.uses_count, c.starts_at, c.expires_at,
                   c.is_active, c.created_at,
                   ct.description, l.code AS lang_code
            FROM coupons c
            LEFT JOIN coupon_translations ct
                ON ct.coupon_id = c.id
                AND ct.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = ct.language_id
            WHERE c.id = :id
        ");
        $stmt->bindValue(':id',      $id,     \PDO::PARAM_INT);
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM coupons WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO coupons (code, type, value, min_order_amount, max_uses, starts_at, expires_at, is_active)
            VALUES (:code, :type, :value, :min_order_amount, :max_uses, :starts_at, :expires_at, :is_active)
        ");
        $stmt->execute([
            ':code'             => $data['code'],
            ':type'             => $data['type']             ?? 'percent',
            ':value'            => $data['value']            ?? 0,
            ':min_order_amount' => $data['min_order_amount'] ?? null,
            ':max_uses'         => $data['max_uses']         ?? null,
            ':starts_at'        => $data['starts_at']        ?? null,
            ':expires_at'       => $data['expires_at']       ?? null,
            ':is_active'        => (int)($data['is_active']  ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['code', 'type', 'value', 'min_order_amount', 'max_uses', 'starts_at', 'expires_at', 'is_active'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]           = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (!$sets) return false;

        $stmt = $this->pdo->prepare('UPDATE coupons SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function incrementUsesCount(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE coupons SET uses_count = uses_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function upsertTranslation(int $couponId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO coupon_translations (coupon_id, language_id, description)
            VALUES (:coupon_id, :language_id, :description)
            ON DUPLICATE KEY UPDATE description = VALUES(description)
        ");
        return $stmt->execute([
            ':coupon_id'   => $couponId,
            ':language_id' => $languageId,
            ':description' => $data['description'] ?? null,
        ]);
    }

    public function getTranslations(int $couponId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ct.*, l.code AS lang_code
            FROM coupon_translations ct
            JOIN languages l ON l.id = ct.language_id
            WHERE ct.coupon_id = :coupon_id
            ORDER BY l.code
        ");
        $stmt->execute([':coupon_id' => $couponId]);
        return $stmt->fetchAll();
    }

    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM languages WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
