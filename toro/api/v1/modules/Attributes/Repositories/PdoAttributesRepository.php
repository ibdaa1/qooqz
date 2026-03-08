<?php
/**
 * TORO — v1/modules/Attributes/Repositories/PdoAttributesRepository.php
 */
declare(strict_types=1);

final class PdoAttributesRepository implements AttributesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $lang     = $filters['lang']      ?? null;
        $type     = $filters['type']      ?? null;
        $isActive = $filters['is_active'] ?? null;
        $limit    = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                a.id, a.slug, a.type, a.sort_order, a.is_active,
                at.name,
                l.code AS lang_code
            FROM attributes a
            LEFT JOIN attribute_translations at
                ON at.attribute_id = a.id
                AND at.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = at.language_id
            WHERE 1=1
        ";

        $params = [':lang_id' => $langId];

        if ($type !== null) {
            $sql .= ' AND a.type = :type';
            $params[':type'] = $type;
        }

        if ($isActive !== null) {
            $sql .= ' AND a.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $sql .= ' ORDER BY a.sort_order ASC, a.id ASC LIMIT :limit OFFSET :offset';

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
        $type     = $filters['type']      ?? null;
        $isActive = $filters['is_active'] ?? null;

        $sql    = 'SELECT COUNT(*) FROM attributes WHERE 1=1';
        $params = [];

        if ($type !== null) {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
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
                a.id, a.slug, a.type, a.sort_order, a.is_active,
                at.name,
                l.code AS lang_code
            FROM attributes a
            LEFT JOIN attribute_translations at
                ON at.attribute_id = a.id
                AND at.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = at.language_id
            WHERE a.id = :id
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
                a.id, a.slug, a.type, a.sort_order, a.is_active,
                at.name,
                l.code AS lang_code
            FROM attributes a
            LEFT JOIN attribute_translations at
                ON at.attribute_id = a.id
                AND at.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = at.language_id
            WHERE a.slug = :slug
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
            INSERT INTO attributes (slug, type, sort_order, is_active)
            VALUES (:slug, :type, :sort_order, :is_active)
        ");
        $stmt->execute([
            ':slug'       => $data['slug'],
            ':type'       => $data['type']       ?? 'select',
            ':sort_order' => $data['sort_order']  ?? 0,
            ':is_active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['slug', 'type', 'sort_order', 'is_active'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE attributes SET ' . implode(', ', $sets) . ' WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM attributes WHERE id = :id')
            ->execute([':id' => $id]);
    }

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $attributeId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO attribute_translations (attribute_id, language_id, name)
            VALUES (:attribute_id, :language_id, :name)
            AS new_row
            ON DUPLICATE KEY UPDATE name = new_row.name
        ");
        return $stmt->execute([
            ':attribute_id' => $attributeId,
            ':language_id'  => $languageId,
            ':name'         => $data['name'],
        ]);
    }

    public function getTranslations(int $attributeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT at.*, l.code AS lang_code, l.name AS lang_name
            FROM attribute_translations at
            JOIN languages l ON l.id = at.language_id
            WHERE at.attribute_id = :attribute_id
            ORDER BY l.sort_order ASC
        ");
        $stmt->execute([':attribute_id' => $attributeId]);
        return $stmt->fetchAll();
    }

    // ── Values ─────────────────────────────────────────────────
    public function getValues(int $attributeId, ?string $lang = null): array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                av.id, av.attribute_id, av.slug, av.color_hex, av.sort_order,
                avt.name,
                l.code AS lang_code
            FROM attribute_values av
            LEFT JOIN attribute_value_translations avt
                ON avt.value_id = av.id
                AND avt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = avt.language_id
            WHERE av.attribute_id = :attribute_id
            ORDER BY av.sort_order ASC, av.id ASC
        ");
        $stmt->bindValue(':lang_id',      $langId,      is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':attribute_id', $attributeId, \PDO::PARAM_INT);
        $stmt->execute();

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
