<?php
namespace V1\modules\Settings\Repositories;

use V1\modules\Settings\Contracts\SettingsRepositoryInterface;
use PDO;

class PdoSettingsRepository implements SettingsRepositoryInterface
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function getPublicSettings(): array
    {
        $stmt = $this->db->prepare("SELECT `key`, `value`, `type` FROM settings WHERE is_public = 1");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $val = $row['value'];
            if ($row['type'] === 'boolean') {
                $val = (bool)$val;
            } elseif ($row['type'] === 'number') {
                $val = strpos($val, '.') !== false ? (float)$val : (int)$val;
            }
            $settings[$row['key']] = $val;
        }
        return $settings;
    }

    public function getAllSettings(): array
    {
        $stmt = $this->db->query("SELECT * FROM settings ORDER BY `group` ASC, `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM settings WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function update(int $id, string $value): bool
    {
        $stmt = $this->db->prepare("UPDATE settings SET `value` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$value, $id]);
    }
}