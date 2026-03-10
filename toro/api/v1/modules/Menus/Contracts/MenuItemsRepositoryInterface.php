<?php
/**
 * TORO — v1/modules/Menus/Contracts/MenuItemsRepositoryInterface.php
 */
declare(strict_types=1);

interface MenuItemsRepositoryInterface
{
    public function findByMenu(int $menuId, ?string $lang = null): array;
    public function findById(int $id): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function reorder(int $menuId, array $orderedIds): void;
    public function upsertTranslation(int $itemId, int $languageId, string $label, ?string $tooltip): void;
}
