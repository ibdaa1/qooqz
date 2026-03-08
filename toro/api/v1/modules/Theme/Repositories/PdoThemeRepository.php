<?php
declare(strict_types=1);

class PdoThemeRepository implements ThemeRepositoryInterface
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function getActiveColors(): array
    {
        $stmt = $this->db->query("SELECT * FROM theme_colors WHERE is_active = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllColors(): array
    {
        $stmt = $this->db->query("SELECT * FROM theme_colors");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateColor(int $id, string $value): bool
    {
        $stmt = $this->db->prepare("UPDATE theme_colors SET `value` = ? WHERE id = ?");
        return $stmt->execute([$value, $id]);
    }
}