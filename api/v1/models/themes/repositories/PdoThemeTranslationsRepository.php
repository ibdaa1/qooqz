<?php
declare(strict_types=1);

final class PdoThemeTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByTheme(int $themeId, ?int $tenantId = null): array
    {
        $sql = "SELECT * FROM theme_translations WHERE theme_id = :theme_id";
        $params = [':theme_id' => $themeId];
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data): int
    {
        $themeId  = (int)($data['theme_id'] ?? 0);
        $langCode = $data['language_code'] ?? '';
        $tenantId = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;

        if (isset($data['id']) && $data['id']) {
            return $this->update((int)$data['id'], $data);
        }

        // Upsert: check existing by (theme_id, language_code, tenant_id)
        $stmt = $this->pdo->prepare(
            "SELECT id FROM theme_translations WHERE theme_id = :theme_id AND language_code = :lang AND tenant_id = :tenant_id"
        );
        $stmt->execute([':theme_id' => $themeId, ':lang' => $langCode, ':tenant_id' => $tenantId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            return $this->update((int)$existingId, $data);
        }

        return $this->create($data);
    }

    private function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO theme_translations (theme_id, language_code, tenant_id, name, description)
             VALUES (:theme_id, :language_code, :tenant_id, :name, :description)"
        );
        $stmt->execute([
            ':theme_id'      => (int)($data['theme_id'] ?? 0),
            ':language_code' => $data['language_code'] ?? '',
            ':tenant_id'     => isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0,
            ':name'          => $data['name'] ?? '',
            ':description'   => $data['description'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function update(int $id, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE theme_translations SET
                name        = :name,
                description = :description
             WHERE id = :id"
        );
        $stmt->execute([
            ':name'        => $data['name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':id'          => $id,
        ]);
        return $id;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM theme_translations WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
