<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CertificatesProductsTranslationsRepository.php';
require_once __DIR__ . '/../validators/CertificatesProductsTranslationsValidator.php';

final class CertificatesProductsTranslationsService
{
    private CertificatesProductsTranslationsRepository $repo;
    private CertificatesProductsTranslationsValidator $validator;

    public function __construct(
        CertificatesProductsTranslationsRepository $repo,
        CertificatesProductsTranslationsValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(array $filters = []): array
    {
        return $this->repo->all($filters);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) throw new RuntimeException('Not found');
        return $row;
    }

    public function save(array $data): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        $id = $this->repo->save($data);
        return $this->get($id);
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) throw new RuntimeException('Failed to delete');
    }
}