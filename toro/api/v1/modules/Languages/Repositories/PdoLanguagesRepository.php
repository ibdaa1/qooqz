<?php
/**
 * TORO — v1/modules/Languages/Repositories/PdoLanguagesRepository.php
 */
declare(strict_types=1);

final class PdoLanguagesRepository implements LanguagesRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $isActive  = $filters['is_active'] ?? null;
        $limit     = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset    = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT * FROM languages WHERE 1=1";
        $params = [];

        if ($isActive !== null) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $sql .= " ORDER BY sort_order ASC, id ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $isActive = $filters['is_active'] ?? null;

        $sql = "SELECT COUNT(*) FROM languages WHERE 1=1";
        $params = [];

        if ($isActive !== null) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM languages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Single by Code ─────────────────────────────────────────
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM languages WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Default language ───────────────────────────────────────
    public function getDefault(): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM languages WHERE is_default = 1 LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO languages (code, name, native, direction, flag_icon, is_active, is_default, sort_order)
            VALUES (:code, :name, :native, :direction, :flag_icon, :is_active, :is_default, :sort_order)
        ");
        $stmt->execute([
            ':code'       => $data['code'],
            ':name'       => $data['name'],
            ':native'     => $data['native'],
            ':direction'  => $data['direction'] ?? 'ltr',
            ':flag_icon'  => $data['flag_icon'] ?? null,
            ':is_active'  => (int)($data['is_active'] ?? 1),
            ':is_default' => (int)($data['is_default'] ?? 0),
            ':sort_order' => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['code', 'name', 'native', 'direction', 'flag_icon', 'is_active', 'is_default', 'sort_order'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE languages SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ── Delete (soft) ──────────────────────────────────────────
    public function delete(int $id): bool
    {
        // لا يمكن حذف اللغة الافتراضية (يتم منعها في الخدمة)
        return $this->pdo->prepare("UPDATE languages SET is_active = 0 WHERE id = :id")->execute([':id' => $id]);
    }

    // ── Helpers ────────────────────────────────────────────────
    public function getDefaultLanguageId(): int
    {
        $id = $this->pdo->query("SELECT id FROM languages WHERE is_default = 1 LIMIT 1")->fetchColumn();
        return $id ? (int)$id : 1; // fallback to 1 if none defined
    }

    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM languages WHERE code = :code AND is_active = 1 LIMIT 1");
        $stmt->execute([':code' => $code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM languages WHERE code = :code";
        $params = [':code' => $code];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() == 0;
    }
}