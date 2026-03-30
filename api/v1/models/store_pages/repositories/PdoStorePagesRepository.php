<?php
declare(strict_types=1);

// api/v1/models/store_pages/repositories/PdoStorePagesRepository.php

final class PdoStorePagesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================
    // Pages
    // =========================================================

    public function allPages(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id, type, slug, is_active, settings, created_at, updated_at
            FROM store_pages
            WHERE tenant_id = :tenantId
            ORDER BY type ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPage(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id, type, slug, is_active, settings, created_at, updated_at
            FROM store_pages
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPageByType(int $tenantId, string $type): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id, type, slug, is_active, settings, created_at, updated_at
            FROM store_pages
            WHERE tenant_id = :tenantId AND type = :type
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function savePage(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData  = $isUpdate ? $this->findPage($tenantId, (int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE store_pages
                SET type       = :type,
                    slug       = :slug,
                    is_active  = :is_active,
                    settings   = :settings,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':type'      => $data['type'] ?? 'store',
                ':slug'      => $data['slug'] ?? null,
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':settings'  => isset($data['settings']) ? (is_string($data['settings']) ? $data['settings'] : json_encode($data['settings'])) : null,
                ':tenantId'  => $tenantId,
                ':id'        => (int)$data['id'],
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO store_pages (tenant_id, type, slug, is_active, settings, created_at, updated_at)
                VALUES (:tenantId, :type, :slug, :is_active, :settings, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    slug       = VALUES(slug),
                    is_active  = VALUES(is_active),
                    settings   = VALUES(settings),
                    updated_at = NOW()
            ");

            $stmt->execute([
                ':tenantId'  => $tenantId,
                ':type'      => $data['type'] ?? 'store',
                ':slug'      => $data['slug'] ?? null,
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':settings'  => isset($data['settings']) ? (is_string($data['settings']) ? $data['settings'] : json_encode($data['settings'])) : null,
            ]);

            $id = (int)$this->pdo->lastInsertId();

            // ON DUPLICATE KEY UPDATE does not set lastInsertId; look up the row
            if ($id === 0) {
                $existing = $this->findPageByType($tenantId, $data['type'] ?? 'store');
                $id = $existing ? (int)$existing['id'] : 0;
            }
        }

        if ($userId) {
            $this->logAction('store_pages', $id, $isUpdate ? 'update' : 'create', $userId, $oldData, $data);
        }

        return $id;
    }

    public function deletePage(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->findPage($tenantId, $id);

        $this->pdo->beginTransaction();

        try {
            // Cascade: delete translations for all sections of this page
            $this->pdo->prepare("
                DELETE sst FROM store_section_translations sst
                INNER JOIN store_sections ss ON sst.section_id = ss.id
                WHERE ss.page_id = :pageId
            ")->execute([':pageId' => $id]);

            // Cascade: delete sections
            $this->pdo->prepare("DELETE FROM store_sections WHERE page_id = :pageId")
                ->execute([':pageId' => $id]);

            // Delete the page
            $stmt = $this->pdo->prepare("
                DELETE FROM store_pages
                WHERE tenant_id = :tenantId AND id = :id
            ");
            $result = $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

            if ($userId && $oldData) {
                $this->logAction('store_pages', $id, 'delete', $userId, $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // =========================================================
    // Sections
    // =========================================================

    public function allSections(int $pageId, string $lang = 'en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ss.id, ss.page_id, ss.type, ss.position, ss.is_active,
                   ss.settings, ss.created_at, ss.updated_at,
                   sst.title   AS title,
                   sst.content AS content
            FROM store_sections ss
            LEFT JOIN store_section_translations sst
                ON ss.id = sst.section_id AND sst.language_code = :lang
            WHERE ss.page_id = :pageId
            ORDER BY ss.position ASC, ss.id ASC
        ");

        $stmt->execute([':pageId' => $pageId, ':lang' => $lang]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSection(int $pageId, int $sectionId, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->findSectionById($pageId, $sectionId);
            if ($row) {
                $row['translations'] = $this->getSectionTranslations($sectionId);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT ss.id, ss.page_id, ss.type, ss.position, ss.is_active,
                   ss.settings, ss.created_at, ss.updated_at,
                   sst.title   AS title,
                   sst.content AS content
            FROM store_sections ss
            LEFT JOIN store_section_translations sst
                ON ss.id = sst.section_id AND sst.language_code = :lang
            WHERE ss.page_id = :pageId AND ss.id = :sectionId
            LIMIT 1
        ");

        $stmt->execute([':pageId' => $pageId, ':sectionId' => $sectionId, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findSectionById(int $pageId, int $sectionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM store_sections
            WHERE page_id = :pageId AND id = :sectionId
            LIMIT 1
        ");

        $stmt->execute([':pageId' => $pageId, ':sectionId' => $sectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveSection(int $pageId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData  = $isUpdate ? $this->findSectionById($pageId, (int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE store_sections
                SET type       = :type,
                    position   = :position,
                    is_active  = :is_active,
                    settings   = :settings,
                    updated_at = NOW()
                WHERE page_id = :pageId AND id = :id
            ");

            $stmt->execute([
                ':type'      => $data['type'],
                ':position'  => (int)($data['position'] ?? 0),
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':settings'  => isset($data['settings']) ? (is_string($data['settings']) ? $data['settings'] : json_encode($data['settings'])) : null,
                ':pageId'    => $pageId,
                ':id'        => (int)$data['id'],
            ]);

            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO store_sections (page_id, type, position, is_active, settings, created_at, updated_at)
                VALUES (:pageId, :type, :position, :is_active, :settings, NOW(), NOW())
            ");

            $stmt->execute([
                ':pageId'    => $pageId,
                ':type'      => $data['type'],
                ':position'  => (int)($data['position'] ?? 0),
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':settings'  => isset($data['settings']) ? (is_string($data['settings']) ? $data['settings'] : json_encode($data['settings'])) : null,
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        // Save translations if provided
        if (!empty($data['translations'])) {
            $this->saveSectionTranslations($id, $data['translations']);
        }

        if ($userId) {
            $this->logAction('store_sections', $id, $isUpdate ? 'update' : 'create', $userId, $oldData, $data);
        }

        return $id;
    }

    public function deleteSection(int $pageId, int $sectionId, ?int $userId = null): bool
    {
        $oldData = $this->findSectionById($pageId, $sectionId);

        $this->pdo->beginTransaction();

        try {
            // Delete translations
            $this->pdo->prepare("DELETE FROM store_section_translations WHERE section_id = :sectionId")
                ->execute([':sectionId' => $sectionId]);

            // Delete section
            $stmt = $this->pdo->prepare("
                DELETE FROM store_sections
                WHERE page_id = :pageId AND id = :sectionId
            ");
            $result = $stmt->execute([':pageId' => $pageId, ':sectionId' => $sectionId]);

            if ($userId && $oldData) {
                $this->logAction('store_sections', $sectionId, 'delete', $userId, $oldData, null);
            }

            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // =========================================================
    // Translations
    // =========================================================

    public function saveSectionTranslations(int $sectionId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO store_section_translations (section_id, language_code, title, content)
            VALUES (:section_id, :lang, :title, :content)
            ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':section_id' => $sectionId,
                ':lang'       => $lang,
                ':title'      => $data['title'] ?? null,
                ':content'    => isset($data['content']) ? (is_string($data['content']) ? $data['content'] : json_encode($data['content'])) : null,
            ]);
        }
    }

    public function getSectionTranslations(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, title, content
            FROM store_section_translations
            WHERE section_id = :section_id
        ");

        $stmt->execute([':section_id' => $sectionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['language_code']] = [
                'title'   => $row['title'],
                'content' => $row['content'],
            ];
        }

        return $translations;
    }

    // =========================================================
    // Reorder
    // =========================================================

    public function reorderSections(int $pageId, array $positions): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE store_sections
            SET position   = :position,
                updated_at = NOW()
            WHERE page_id = :pageId AND id = :id
        ");

        foreach ($positions as $item) {
            $stmt->execute([
                ':position' => (int)$item['position'],
                ':pageId'   => $pageId,
                ':id'       => (int)$item['id'],
            ]);
        }
    }

    // =========================================================
    // Audit log
    // =========================================================

    public function logAction(string $table, int $recordId, string $action, ?int $userId, ?array $oldData = null, ?array $newData = null): void
    {
        if (!$userId) {
            return;
        }

        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode([
                'old' => $oldData,
                'new' => $newData,
            ]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (:tenantId, :userId, :entityType, :entityId, :action, :changes, :ip, NOW())
        ");

        $stmt->execute([
            ':tenantId'   => 0,
            ':userId'     => $userId,
            ':entityType' => $table,
            ':entityId'   => $recordId,
            ':action'     => $action,
            ':changes'    => $changes,
            ':ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
