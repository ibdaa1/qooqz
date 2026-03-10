<?php
/**
 * TORO — v1/modules/Translations/Repositories/PdoTranslationsRepository.php
 */
declare(strict_types=1);

final class PdoTranslationsRepository implements TranslationsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    // ── Keys ───────────────────────────────────────────────────
    public function findAllKeys(array $filters = []): array
    {
        $context = $filters['context'] ?? null;
        $search  = $filters['search']  ?? null;
        $limit   = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset  = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT * FROM translation_keys WHERE 1=1";
        $params = [];

        if ($context !== null) {
            $sql .= " AND context = :context";
            $params[':context'] = $context;
        }

        if ($search !== null) {
            $sql .= " AND key_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY id ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAllKeys(array $filters = []): int
    {
        $context = $filters['context'] ?? null;
        $search  = $filters['search']  ?? null;

        $sql = "SELECT COUNT(*) FROM translation_keys WHERE 1=1";
        $params = [];

        if ($context !== null) {
            $sql .= " AND context = :context";
            $params[':context'] = $context;
        }

        if ($search !== null) {
            $sql .= " AND key_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findKeyById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM translation_keys WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findKeyByName(string $keyName): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM translation_keys WHERE key_name = :key_name LIMIT 1");
        $stmt->execute([':key_name' => $keyName]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createKey(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO translation_keys (key_name, context)
            VALUES (:key_name, :context)
        ");
        $stmt->execute([
            ':key_name' => $data['key_name'],
            ':context'  => $data['context'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateKey(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['key_name', 'context'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE translation_keys SET ' . implode(', ', $sets) . ' WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function deleteKey(int $id): bool
    {
        // بسبب ON DELETE CASCADE، سيتم حذف القيم المرتبطة تلقائياً
        return $this->pdo->prepare("DELETE FROM translation_keys WHERE id = :id")->execute([':id' => $id]);
    }

    // ── Values ─────────────────────────────────────────────────
    public function getValue(int $keyId, int $languageId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT value FROM translation_values
            WHERE key_id = :key_id AND language_id = :language_id
            LIMIT 1
        ");
        $stmt->execute([
            ':key_id'      => $keyId,
            ':language_id' => $languageId,
        ]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string)$value : null;
    }

    public function getValuesByKeyId(int $keyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT tv.*, l.code AS language_code, l.name AS language_name
            FROM translation_values tv
            JOIN languages l ON l.id = tv.language_id
            WHERE tv.key_id = :key_id
            ORDER BY l.sort_order ASC
        ");
        $stmt->execute([':key_id' => $keyId]);
        return $stmt->fetchAll();
    }

    public function getValuesByLanguageId(int $languageId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT tv.*, tk.key_name
            FROM translation_values tv
            JOIN translation_keys tk ON tk.id = tv.key_id
            WHERE tv.language_id = :language_id
            ORDER BY tk.key_name ASC
        ");
        $stmt->execute([':language_id' => $languageId]);
        return $stmt->fetchAll();
    }

    public function upsertValue(int $keyId, int $languageId, string $value): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO translation_values (key_id, language_id, value)
            VALUES (:key_id, :language_id, :value)
            ON DUPLICATE KEY UPDATE value = :value
        ");
        return $stmt->execute([
            ':key_id'      => $keyId,
            ':language_id' => $languageId,
            ':value'       => $value,
        ]);
    }

    public function deleteValue(int $keyId, int $languageId): bool
    {
        return $this->pdo->prepare("
            DELETE FROM translation_values
            WHERE key_id = :key_id AND language_id = :language_id
        ")->execute([
            ':key_id'      => $keyId,
            ':language_id' => $languageId,
        ]);
    }

    public function deleteAllValuesForKey(int $keyId): bool
    {
        return $this->pdo->prepare("DELETE FROM translation_values WHERE key_id = :key_id")
            ->execute([':key_id' => $keyId]);
    }

    // ── Bulk operations ────────────────────────────────────────
    public function getTranslationsByKeys(array $keys, int $languageId): array
    {
        if (empty($keys)) return [];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "
            SELECT tk.key_name, tv.value
            FROM translation_keys tk
            LEFT JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_id = ?
            WHERE tk.key_name IN ({$placeholders})
        ";
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$languageId], $keys);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // key_name => value
    }

    public function getAllTranslationsForLanguage(int $languageId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT tk.key_name, tv.value
            FROM translation_keys tk
            LEFT JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_id = :lang_id
            ORDER BY tk.key_name ASC
        ");
        $stmt->execute([':lang_id' => $languageId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM languages WHERE code = :code AND is_active = 1 LIMIT 1");
        $stmt->execute([':code' => $code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function getDefaultLanguageId(): int
    {
        $id = $this->pdo->query("SELECT id FROM languages WHERE is_default = 1 LIMIT 1")->fetchColumn();
        return $id ? (int)$id : 1;
    }
}