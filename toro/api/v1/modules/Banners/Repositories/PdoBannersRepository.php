<?php
/**
 * TORO — v1/modules/Banners/Repositories/PdoBannersRepository.php
 */
declare(strict_types=1);

final class PdoBannersRepository implements BannersRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $lang     = $filters['lang']      ?? null;
        $position = $filters['position']  ?? null;
        $isActive = $filters['is_active'] ?? null;
        $limit    = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                b.id, b.position, b.link_url, b.sort_order,
                b.starts_at, b.ends_at, b.is_active, b.created_at,
                bt.title, bt.subtitle, bt.cta_text, bt.alt_text,
                l.code AS lang_code
            FROM banners b
            LEFT JOIN banner_translations bt
                ON bt.banner_id = b.id
                AND bt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = bt.language_id
            WHERE 1=1
        ";

        $params = [':lang_id' => $langId];

        if ($position !== null) {
            $sql .= ' AND b.position = :position';
            $params[':position'] = $position;
        }

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
        $position = $filters['position']  ?? null;
        $isActive = $filters['is_active'] ?? null;

        $sql    = 'SELECT COUNT(*) FROM banners WHERE 1=1';
        $params = [];

        if ($position !== null) {
            $sql .= ' AND position = :position';
            $params[':position'] = $position;
        }
        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Find by ID ─────────────────────────────────────────────
    public function findById(int $id, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                b.id, b.position, b.link_url, b.sort_order,
                b.starts_at, b.ends_at, b.is_active, b.created_at,
                bt.title, bt.subtitle, bt.cta_text, bt.alt_text,
                l.code AS lang_code
            FROM banners b
            LEFT JOIN banner_translations bt
                ON bt.banner_id = b.id
                AND bt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = bt.language_id
            WHERE b.id = :id
        ");
        $stmt->bindValue(':id',      $id,     \PDO::PARAM_INT);
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO banners (position, link_url, sort_order, starts_at, ends_at, is_active)
            VALUES (:position, :link_url, :sort_order, :starts_at, :ends_at, :is_active)
        ");
        $stmt->execute([
            ':position'   => $data['position'],
            ':link_url'   => $data['link_url']   ?? null,
            ':sort_order' => $data['sort_order']  ?? 0,
            ':starts_at'  => $data['starts_at']   ?? null,
            ':ends_at'    => $data['ends_at']      ?? null,
            ':is_active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        $allowed = ['position', 'link_url', 'sort_order', 'starts_at', 'ends_at', 'is_active'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]         = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (!$sets) return false;

        $stmt = $this->pdo->prepare('UPDATE banners SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM banners WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $bannerId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO banner_translations (banner_id, language_id, title, subtitle, cta_text, alt_text)
            VALUES (:banner_id, :language_id, :title, :subtitle, :cta_text, :alt_text)
            ON DUPLICATE KEY UPDATE
                title    = VALUES(title),
                subtitle = VALUES(subtitle),
                cta_text = VALUES(cta_text),
                alt_text = VALUES(alt_text)
        ");
        return $stmt->execute([
            ':banner_id'   => $bannerId,
            ':language_id' => $languageId,
            ':title'       => $data['title']    ?? null,
            ':subtitle'    => $data['subtitle'] ?? null,
            ':cta_text'    => $data['cta_text'] ?? null,
            ':alt_text'    => $data['alt_text'] ?? null,
        ]);
    }

    public function getTranslations(int $bannerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bt.*, l.code AS lang_code
            FROM banner_translations bt
            JOIN languages l ON l.id = bt.language_id
            WHERE bt.banner_id = :banner_id
            ORDER BY l.code
        ");
        $stmt->execute([':banner_id' => $bannerId]);
        return $stmt->fetchAll();
    }

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM languages WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
