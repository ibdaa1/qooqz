<?php
/**
 * TORO — v1/modules/ProductReviews/Services/ProductReviewsService.php
 */
declare(strict_types=1);

final class ProductReviewsService
{
    public function __construct(
        private readonly ProductReviewsRepositoryInterface $repo
    ) {}

    public function getForProduct(int $productId, array $filters = []): array
    {
        $items = $this->repo->findByProduct($productId, $filters);
        $total = $this->repo->countByProduct($productId, $filters);
        return ['data' => $items, 'total' => $total];
    }

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function create(CreateReviewDTO $dto): array
    {
        $id = $this->repo->create([
            'product_id' => $dto->productId,
            'user_id'    => $dto->userId,
            'rating'     => $dto->rating,
            'title'      => $dto->title,
            'body'       => $dto->body,
        ]);
        return $this->repo->findById($id) ?? ['id' => $id];
    }

    public function update(int $id, array $data): ?array
    {
        $this->repo->update($id, $data);
        return $this->repo->findById($id);
    }

    public function approve(int $id): bool
    {
        return $this->repo->approve($id);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
