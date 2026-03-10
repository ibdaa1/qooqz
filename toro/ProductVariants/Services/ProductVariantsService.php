<?php
/**
 * TORO — v1/modules/ProductVariants/Services/ProductVariantsService.php
 */
declare(strict_types=1);

final class ProductVariantsService
{
    public function __construct(
        private readonly ProductVariantsRepositoryInterface $repo
    ) {}

    public function getForProduct(int $productId): array
    {
        return $this->repo->findByProduct($productId);
    }

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function getBySku(string $sku): ?array
    {
        return $this->repo->findBySku($sku);
    }

    public function create(CreateVariantDTO $dto): array
    {
        $id = $this->repo->create([
            'product_id' => $dto->productId,
            'size_id'    => $dto->sizeId,
            'sku'        => $dto->sku,
            'price'      => $dto->price,
            'sale_price' => $dto->salePrice,
            'stock_qty'  => $dto->stockQty,
            'is_active'  => $dto->isActive,
        ]);
        return $this->repo->findById($id) ?? ['id' => $id];
    }

    public function update(int $id, UpdateVariantDTO $dto): ?array
    {
        $data = array_filter([
            'size_id'    => $dto->sizeId,
            'sku'        => $dto->sku,
            'price'      => $dto->price,
            'sale_price' => $dto->salePrice,
            'stock_qty'  => $dto->stockQty,
            'is_active'  => isset($dto->isActive) ? (int)$dto->isActive : null,
        ], fn($v) => $v !== null);

        if (empty($data)) return $this->repo->findById($id);

        $this->repo->update($id, $data);
        return $this->repo->findById($id);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
