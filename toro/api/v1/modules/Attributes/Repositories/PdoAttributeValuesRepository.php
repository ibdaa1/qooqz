<?php
/**
 * TORO — v1/modules/Attributes/Repositories/PdoAttributeValuesRepository.php
 */
declare(strict_types=1);

final class PdoAttributeValuesRepository implements AttributeValuesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id, ?string $lang = null): ?array
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
            WHERE av.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    // ── All values for an attribute ───────────────────────────
    public function findByAttributeId(int $attributeId, ?string $lang = null): array
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

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO attribute_values (attribute_id, slug, color_hex, sort_order)
            VALUES (:attribute_id, :slug, :color_hex, :sort_order)
        ");
        $stmt->execute([
            ':attribute_id' => $data['attribute_id'],
            ':slug'         => $data['slug'],
            ':color_hex'    => $data['color_hex']  ?? null,
            ':sort_order'   => $data['sort_order'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['slug', 'color_hex', 'sort_order'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE attribute_values SET ' . implode(', ', $sets) . ' WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM attribute_values WHERE id = :id')
            ->execute([':id' => $id]);
    }

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $valueId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO attribute_value_translations (value_id, language_id, name)
            VALUES (:value_id, :language_id, :name)
            AS new_row
            ON DUPLICATE KEY UPDATE name = new_row.name
        ");
        return $stmt->execute([
            ':value_id'    => $valueId,
            ':language_id' => $languageId,
            ':name'        => $data['name'],
        ]);
    }

    public function getTranslations(int $valueId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT avt.*, l.code AS lang_code, l.name AS lang_name
            FROM attribute_value_translations avt
            JOIN languages l ON l.id = avt.language_id
            WHERE avt.value_id = :value_id
            ORDER BY l.sort_order ASC
        ");
        $stmt->execute([':value_id' => $valueId]);
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
}
