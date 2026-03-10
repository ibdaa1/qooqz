<?php
/**
 * TORO — v1/modules/Categories/Repositories/PdoCategoriesRepository.php
 */
declare(strict_types=1);

final class PdoCategoriesRepository implements CategoriesRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $lang      = $filters['lang']      ?? null;
        $parentId  = $filters['parent_id'] ?? null;
        $isActive  = $filters['is_active'] ?? null;
        $limit     = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset    = max(0, (int)($filters['offset'] ?? 0));

        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                c.id, c.parent_id, c.slug, c.sort_order, c.is_active,
                c.created_at, c.updated_at,
                ct.name, ct.description, ct.meta_title, ct.meta_desc,
                l.code AS lang_code
            FROM categories c
            LEFT JOIN category_translations ct
                ON ct.category_id = c.id
                AND ct.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = ct.language_id
            WHERE 1=1
        ";

        $params = [':lang_id' => $langId];

        if ($parentId !== null) {
            $sql .= ' AND c.parent_id = :parent_id';
            $params[':parent_id'] = (int)$parentId;
        }

        if ($isActive !== null) {
            $sql .= ' AND c.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $sql .= ' ORDER BY c.sort_order ASC, c.id ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_null($val) ? PDO::PARAM_NULL : (is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR));
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ── Count ──────────────────────────────────────────────────
    public function countAll(array $filters = []): int
    {
        $parentId = $filters['parent_id'] ?? null;
        $isActive = $filters['is_active'] ?? null;

        $sql    = 'SELECT COUNT(*) FROM categories WHERE 1=1';
        $params = [];

        if ($parentId !== null) {
            $sql .= ' AND parent_id = :parent_id';
            $params[':parent_id'] = (int)$parentId;
        }

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
                c.id, c.parent_id, c.slug, c.sort_order, c.is_active,
                c.created_at, c.updated_at,
                ct.name, ct.description, ct.meta_title, ct.meta_desc,
                l.code AS lang_code
            FROM categories c
            LEFT JOIN category_translations ct
                ON ct.category_id = c.id
                AND ct.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = ct.language_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    // ── Single by Slug ─────────────────────────────────────────
    public function findBySlug(string $slug, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                c.id, c.parent_id, c.slug, c.sort_order, c.is_active,
                c.created_at, c.updated_at,
                ct.name, ct.description, ct.meta_title, ct.meta_desc,
                l.code AS lang_code
            FROM categories c
            LEFT JOIN category_translations ct
                ON ct.category_id = c.id
                AND ct.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = ct.language_id
            WHERE c.slug = :slug
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO categories (parent_id, slug, sort_order, is_active)
            VALUES (:parent_id, :slug, :sort_order, :is_active)
        ");
        $stmt->execute([
            ':parent_id'  => $data['parent_id']  ?? null,
            ':slug'       => $data['slug'],
            ':sort_order' => $data['sort_order']  ?? 0,
            ':is_active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['parent_id', 'slug', 'sort_order', 'is_active'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE categories SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ── Delete (soft — sets is_active=0) ─────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM categories WHERE id = :id')
            ->execute([':id' => $id]);
    }

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $categoryId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO category_translations
                (category_id, language_id, name, description, meta_title, meta_desc)
            VALUES
                (:category_id, :language_id, :name, :description, :meta_title, :meta_desc)
            AS new_row
            ON DUPLICATE KEY UPDATE
                name        = new_row.name,
                description = new_row.description,
                meta_title  = new_row.meta_title,
                meta_desc   = new_row.meta_desc
        ");
        return $stmt->execute([
            ':category_id' => $categoryId,
            ':language_id' => $languageId,
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null,
            ':meta_title'  => $data['meta_title']  ?? null,
            ':meta_desc'   => $data['meta_desc']   ?? null,
        ]);
    }

    public function getTranslations(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ct.*, l.code AS lang_code, l.name AS lang_name
            FROM category_translations ct
            JOIN languages l ON l.id = ct.language_id
            WHERE ct.category_id = :category_id
            ORDER BY l.sort_order ASC
        ");
        $stmt->execute([':category_id' => $categoryId]);
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