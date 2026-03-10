<?php
/**
 * TORO — v1/modules/Menus/Repositories/PdoMenuItemsRepository.php
 */
declare(strict_types=1);

final class PdoMenuItemsRepository implements MenuItemsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── All items for a menu (with translations) ───────────────
    public function findByMenu(int $menuId, ?string $lang = null): array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                mi.id, mi.menu_id, mi.parent_id, mi.type, mi.reference_id,
                mi.url, mi.icon, mi.target, mi.sort_order, mi.is_active,
                mit.label, mit.tooltip,
                l.code AS lang_code
            FROM menu_items mi
            LEFT JOIN menu_item_translations mit
                ON mit.menu_item_id = mi.id
                AND mit.language_id = COALESCE(:lang_id,
                    (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = mit.language_id
            WHERE mi.menu_id = :menu_id
            ORDER BY mi.sort_order, mi.id
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':menu_id', $menuId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id, menu_id, parent_id, type, reference_id,
                url, icon, target, sort_order, is_active
            FROM menu_items WHERE id = :id LIMIT 1
        ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO menu_items
                (menu_id, parent_id, type, reference_id, url, icon, target, sort_order, is_active)
            VALUES
                (:menu_id, :parent_id, :type, :reference_id, :url, :icon, :target, :sort_order, :is_active)
        ");
        $stmt->execute([
            ':menu_id'      => $data['menu_id'],
            ':parent_id'    => $data['parent_id']    ?? null,
            ':type'         => $data['type']         ?? 'link',
            ':reference_id' => $data['reference_id'] ?? null,
            ':url'          => $data['url']           ?? null,
            ':icon'         => $data['icon']          ?? null,
            ':target'       => $data['target']        ?? '_self',
            ':sort_order'   => $data['sort_order']    ?? 0,
            ':is_active'    => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['parent_id', 'type', 'reference_id', 'url', 'icon', 'target', 'sort_order', 'is_active'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        return $this->pdo->prepare(
            'UPDATE menu_items SET ' . implode(', ', $sets) . ' WHERE id = :__id'
        )->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM menu_items WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // ── Reorder items ──────────────────────────────────────────
    public function reorder(int $menuId, array $orderedIds): void
    {
        $upd = $this->pdo->prepare(
            'UPDATE menu_items SET sort_order = :order WHERE id = :id AND menu_id = :mid'
        );
        foreach ($orderedIds as $order => $itemId) {
            $upd->bindValue(':order', $order,   \PDO::PARAM_INT);
            $upd->bindValue(':id',    (int)$itemId, \PDO::PARAM_INT);
            $upd->bindValue(':mid',   $menuId,  \PDO::PARAM_INT);
            $upd->execute();
        }
    }

    // ── Upsert translation ─────────────────────────────────────
    public function upsertTranslation(int $itemId, int $languageId, string $label, ?string $tooltip): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO menu_item_translations (menu_item_id, language_id, label, tooltip)
            VALUES (:item_id, :lang_id, :label, :tooltip)
            ON DUPLICATE KEY UPDATE label = VALUES(label), tooltip = VALUES(tooltip)
        ");
        $stmt->bindValue(':item_id', $itemId,    \PDO::PARAM_INT);
        $stmt->bindValue(':lang_id', $languageId, \PDO::PARAM_INT);
        $stmt->bindValue(':label',   $label);
        $stmt->bindValue(':tooltip', $tooltip);
        $stmt->execute();
    }

    // ── Helpers ────────────────────────────────────────────────
    private function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM languages WHERE code = :code LIMIT 1');
        $stmt->bindValue(':code', $code);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}
