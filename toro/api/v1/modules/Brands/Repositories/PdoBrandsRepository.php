<?php
/**
 * TORO — v1/modules/Brands/Repositories/PdoBrandsRepository.php
 */
declare(strict_types=1);

final class PdoBrandsRepository implements BrandsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $lang     = $filters['lang']      ?? null;
        $isActive = $filters['is_active'] ?? null;
        $limit    = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                b.id, b.slug, b.website, b.sort_order, b.is_active, b.created_at,
                bt.name, bt.description,
                l.code AS lang_code
            FROM brands b
            LEFT JOIN brand_translations bt
                ON bt.brand_id = b.id
                AND bt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = bt.language_id
            WHERE 1=1
        ";

        $params = [':lang_id' => $langId];

        if ($isActive !== null) {
            $sql .= ' AND b.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $sql .= ' ORDER BY b.sort_order ASC, b.id ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_null($val) ? \PDO::PARAM_NULL : (is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ── Count ──────────────────────────────────────────────────
    public function countAll(array $filters = []): int
    {
        $isActive = $filters['is_active'] ?? null;

        $sql    = 'SELECT COUNT(*) FROM brands WHERE 1=1';
        $params = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                b.id, b.slug, b.website, b.sort_order, b.is_active, b.created_at,
                bt.name, bt.description,
                l.code AS lang_code
            FROM brands b
            LEFT JOIN brand_translations bt
                ON bt.brand_id = b.id
                AND bt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = bt.language_id
            WHERE b.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    // ── Single by Slug ─────────────────────────────────────────
    public function findBySlug(string $slug, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                b.id, b.slug, b.website, b.sort_order, b.is_active, b.created_at,
                bt.name, bt.description,
                l.code AS lang_code
            FROM brands b
            LEFT JOIN brand_translations bt
                ON bt.brand_id = b.id
                AND bt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = bt.language_id
            WHERE b.slug = :slug
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':slug', $slug, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO brands (slug, website, sort_order, is_active)
            VALUES (:slug, :website, :sort_order, :is_active)
        ");
        $stmt->execute([
            ':slug'       => $data['slug'],
            ':website'    => $data['website']     ?? null,
            ':sort_order' => $data['sort_order']  ?? 0,
            ':is_active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['slug', 'website', 'sort_order', 'is_active'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE brands SET ' . implode(', ', $sets) . ' WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM brands WHERE id = :id')
            ->execute([':id' => $id]);
    }

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $brandId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO brand_translations
                (brand_id, language_id, name, description)
            VALUES
                (:brand_id, :language_id, :name, :description)
            AS new_row
            ON DUPLICATE KEY UPDATE
                name        = new_row.name,
                description = new_row.description
        ");
        return $stmt->execute([
            ':brand_id'    => $brandId,
            ':language_id' => $languageId,
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null,
        ]);
    }

    public function getTranslations(int $brandId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bt.*, l.code AS lang_code, l.name AS lang_name
            FROM brand_translations bt
            JOIN languages l ON l.id = bt.language_id
            WHERE bt.brand_id = :brand_id
            ORDER BY l.sort_order ASC
        ");
        $stmt->execute([':brand_id' => $brandId]);
        return $stmt->fetchAll();
    }

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM languages WHERE code = :code AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':code' => $code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function getDefaultLanguageId(): int
    {
        $id = $this->pdo->query('SELECT id FROM languages WHERE is_default = 1 LIMIT 1')->fetchColumn();
        return $id ? (int)$id : 1;
    }
}
