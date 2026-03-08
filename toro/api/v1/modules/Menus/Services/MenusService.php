<?php
/**
 * TORO — v1/modules/Menus/Services/MenusService.php
 */
declare(strict_types=1);

final class MenusService
{
    public function __construct(
        private readonly MenusRepositoryInterface     $menuRepo,
        private readonly MenuItemsRepositoryInterface $itemRepo
    ) {}

    // ── Menus ──────────────────────────────────────────────────
    public function listMenus(): array
    {
        return $this->menuRepo->findAll();
    }

    public function getMenuById(int $id): ?array
    {
        return $this->menuRepo->findById($id);
    }

    public function getMenuBySlug(string $slug, ?string $lang = null): ?array
    {
        $menu = $this->menuRepo->findBySlug($slug);
        if (!$menu) return null;
        $menu['items'] = $this->getTreeForMenu((int)$menu['id'], $lang);
        return $menu;
    }

    public function createMenu(CreateMenuDTO $dto): array
    {
        $id   = $this->menuRepo->create([
            'slug'      => $dto->slug,
            'is_active' => $dto->isActive,
        ]);
        return $this->menuRepo->findById($id) ?? ['id' => $id];
    }

    public function updateMenu(int $id, array $data): ?array
    {
        $this->menuRepo->update($id, $data);
        return $this->menuRepo->findById($id);
    }

    public function deleteMenu(int $id): bool
    {
        return $this->menuRepo->delete($id);
    }

    // ── Menu Items ─────────────────────────────────────────────
    public function getItemsForMenu(int $menuId, ?string $lang = null): array
    {
        return $this->itemRepo->findByMenu($menuId, $lang);
    }

    /** Returns a nested tree structure (parent → children) */
    public function getTreeForMenu(int $menuId, ?string $lang = null): array
    {
        $flat     = $this->itemRepo->findByMenu($menuId, $lang);
        $indexed  = [];
        $tree     = [];

        foreach ($flat as $item) {
            $item['children'] = [];
            $indexed[(int)$item['id']] = $item;
        }
        foreach ($indexed as $id => $item) {
            $pid = $item['parent_id'];
            if ($pid && isset($indexed[(int)$pid])) {
                $indexed[(int)$pid]['children'][] = &$indexed[$id];
            } else {
                $tree[] = &$indexed[$id];
            }
        }
        return $tree;
    }

    public function createItem(CreateMenuItemDTO $dto): array
    {
        $id = $this->itemRepo->create([
            'menu_id'      => $dto->menuId,
            'parent_id'    => $dto->parentId,
            'type'         => $dto->type,
            'reference_id' => $dto->referenceId,
            'url'          => $dto->url,
            'icon'         => $dto->icon,
            'target'       => $dto->target,
            'sort_order'   => $dto->sortOrder,
            'is_active'    => $dto->isActive,
        ]);

        // Save translations
        foreach ($dto->translations as $t) {
            if (!empty($t['language_id']) && !empty($t['label'])) {
                $this->itemRepo->upsertTranslation(
                    $id,
                    (int)$t['language_id'],
                    $t['label'],
                    $t['tooltip'] ?? null
                );
            }
        }

        return $this->itemRepo->findById($id) ?? ['id' => $id];
    }

    public function updateItem(int $id, array $data): ?array
    {
        $this->itemRepo->update($id, $data);

        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $t) {
                if (!empty($t['language_id']) && !empty($t['label'])) {
                    $this->itemRepo->upsertTranslation(
                        $id,
                        (int)$t['language_id'],
                        $t['label'],
                        $t['tooltip'] ?? null
                    );
                }
            }
        }

        return $this->itemRepo->findById($id);
    }

    public function deleteItem(int $id): bool
    {
        return $this->itemRepo->delete($id);
    }

    public function reorderItems(int $menuId, array $orderedIds): void
    {
        $this->itemRepo->reorder($menuId, $orderedIds);
    }
}
