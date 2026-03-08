<?php
/**
 * TORO — v1/modules/Pages/Repositories/PdoPagesRepository.php
 */
declare(strict_types=1);

final class PdoPagesRepository implements PagesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(array $filters = []): array
    {
        $lang     = $filters['lang']      ?? null;
        $isActive = $filters['is_active'] ?? null;
        $limit    = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));
        $langId   = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                p.id, p.slug, p.template, p.is_active, p.created_at,
                pt.title, pt.meta_title, pt.meta_desc,
                l.code AS lang_code
            FROM pages p
            LEFT JOIN page_translations pt
                ON pt.page_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = pt.language_id
            WHERE 1=1
        ";

        $params = [':lang_id' => $langId];

        if ($isActive !== null) {
            $sql .= ' AND p.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $sql .= ' ORDER BY p.id ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_null($val) ? \PDO::PARAM_NULL : (is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $isActive = $filters['is_active'] ?? null;
        $sql    = 'SELECT COUNT(*) FROM pages WHERE 1=1';
        $params = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.slug, p.template, p.is_active, p.created_at,
                   pt.title, pt.content, pt.meta_title, pt.meta_desc,
                   l.code AS lang_code
            FROM pages p
            LEFT JOIN page_translations pt
                ON pt.page_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = pt.language_id
            WHERE p.id = :id
        ");
        $stmt->bindValue(':id',      $id,     \PDO::PARAM_INT);
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.slug, p.template, p.is_active, p.created_at,
                   pt.title, pt.content, pt.meta_title, pt.meta_desc,
                   l.code AS lang_code
            FROM pages p
            LEFT JOIN page_translations pt
                ON pt.page_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = pt.language_id
            WHERE p.slug = :slug
        ");
        $stmt->bindValue(':slug',    $slug,   \PDO::PARAM_STR);
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pages (slug, template, is_active)
            VALUES (:slug, :template, :is_active)
        ");
        $stmt->execute([
            ':slug'      => $data['slug'],
            ':template'  => $data['template']  ?? 'default',
            ':is_active' => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['slug', 'template', 'is_active'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]           = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (!$sets) return false;

        $stmt = $this->pdo->prepare('UPDATE pages SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM pages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function upsertTranslation(int $pageId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO page_translations (page_id, language_id, title, content, meta_title, meta_desc)
            VALUES (:page_id, :language_id, :title, :content, :meta_title, :meta_desc)
            ON DUPLICATE KEY UPDATE
                title      = VALUES(title),
                content    = VALUES(content),
                meta_title = VALUES(meta_title),
                meta_desc  = VALUES(meta_desc)
        ");
        return $stmt->execute([
            ':page_id'     => $pageId,
            ':language_id' => $languageId,
            ':title'       => $data['title']      ?? '',
            ':content'     => $data['content']    ?? '',
            ':meta_title'  => $data['meta_title'] ?? null,
            ':meta_desc'   => $data['meta_desc']  ?? null,
        ]);
    }

    public function getTranslations(int $pageId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pt.*, l.code AS lang_code
            FROM page_translations pt
            JOIN languages l ON l.id = pt.language_id
            WHERE pt.page_id = :page_id
            ORDER BY l.code
        ");
        $stmt->execute([':page_id' => $pageId]);
        return $stmt->fetchAll();
    }

    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM languages WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
