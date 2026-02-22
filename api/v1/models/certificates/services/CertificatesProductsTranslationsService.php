<?php
declare(strict_types=1);

final class CertificatesProductsTranslationsService
{
    private CertificatesProductsTranslationsRepository $repo;
    private CertificatesProductsTranslationsValidator  $validator;

    public function __construct(
        CertificatesProductsTranslationsRepository $repo,
        CertificatesProductsTranslationsValidator  $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        return $this->repo->all($filters, $orderBy, $orderDir, $limit, $offset);
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * إنشاء أو تحديث ترجمة (upsert على product_id + language_code)
     */
    public function create(array $data): int
    {
        $this->validator->validate($data, false);
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * حذف بـ product_id + language_code — يُستدعى من الـ JS عند حذف panel ترجمة
     */
    public function deleteByProductAndLang(int $productId, string $languageCode): bool
    {
        return $this->repo->deleteByProductAndLang($productId, $languageCode);
    }
}