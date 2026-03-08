<?php
/**
 * TORO — v1/modules/Products/Services/ProductsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class ProductsService
{
    public function __construct(
        private readonly ProductsRepositoryInterface $repo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════
    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE
    // ══════════════════════════════════════════════════════════
    public function getById(int $id, ?string $lang = null): array
    {
        $product = $this->repo->findById($id, $lang);
        if (!$product) throw new NotFoundException("المنتج #{$id} غير موجود");
        return $product;
    }

    public function getBySku(string $sku, ?string $lang = null): array
    {
        $product = $this->repo->findBySku($sku, $lang);
        if (!$product) throw new NotFoundException("المنتج '{$sku}' غير موجود");
        return $product;
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreateProductDTO $dto, int $actorId): array
    {
        if ($this->repo->findBySku($dto->sku)) {
            throw new ValidationException(
                'هذا الـ SKU مستخدم مسبقاً',
                ['sku' => 'يجب أن يكون فريداً']
            );
        }

        $productId = $this->repo->create([
            'sku'          => $dto->sku,
            'brand_id'     => $dto->brandId,
            'category_id'  => $dto->categoryId,
            'type'         => $dto->type,
            'base_price'   => $dto->basePrice,
            'sale_price'   => $dto->salePrice,
            'stock_qty'    => $dto->stockQty,
            'weight_grams' => $dto->weightGrams,
            'is_featured'  => $dto->isFeatured,
            'is_active'    => $dto->isActive,
            'sort_order'   => $dto->sortOrder,
        ]);

        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($productId, $langId, $t);
        }

        AuditLogger::log('product_created', $actorId, 'products', $productId);

        return array_merge(
            $this->repo->findById($productId) ?? [],
            ['translations' => $this->repo->getTranslations($productId)]
        );
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateProductDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("المنتج #{$id} غير موجود");

        if ($dto->sku !== null && $dto->sku !== $existing['sku']) {
            $conflict = $this->repo->findBySku($dto->sku);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException('هذا الـ SKU مستخدم مسبقاً', ['sku' => 'يجب أن يكون فريداً']);
            }
        }

        $updateData = array_filter([
            'sku'          => $dto->sku,
            'brand_id'     => $dto->brandId,
            'category_id'  => $dto->categoryId,
            'type'         => $dto->type,
            'base_price'   => $dto->basePrice,
            'sale_price'   => $dto->salePrice,
            'stock_qty'    => $dto->stockQty,
            'weight_grams' => $dto->weightGrams,
            'is_featured'  => $dto->isFeatured !== null ? (int)$dto->isFeatured : null,
            'is_active'    => $dto->isActive   !== null ? (int)$dto->isActive   : null,
            'sort_order'   => $dto->sortOrder,
        ], fn($v) => $v !== null);

        if ($updateData) $this->repo->update($id, $updateData);

        if ($dto->translations !== null) {
            foreach ($dto->translations as $t) {
                $langId = $this->repo->resolveLanguageId($t['lang']);
                if ($langId === null) continue;
                $this->repo->upsertTranslation($id, $langId, $t);
            }
        }

        AuditLogger::log('product_updated', $actorId, 'products', $id);

        return array_merge(
            $this->repo->findById($id) ?? [],
            ['translations' => $this->repo->getTranslations($id)]
        );
    }

    // ══════════════════════════════════════════════════════════
    // DELETE (soft)
    // ══════════════════════════════════════════════════════════
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("المنتج #{$id} غير موجود");

        $this->repo->softDelete($id);
        AuditLogger::log('product_deleted', $actorId, 'products', $id);
    }

    // ══════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ══════════════════════════════════════════════════════════
    public function getTranslations(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("المنتج #{$id} غير موجود");
        return $this->repo->getTranslations($id);
    }

    // ══════════════════════════════════════════════════════════
    // IMAGES
    // ══════════════════════════════════════════════════════════
    public function getImages(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("المنتج #{$id} غير موجود");
        return $this->repo->getImages($id);
    }
}
