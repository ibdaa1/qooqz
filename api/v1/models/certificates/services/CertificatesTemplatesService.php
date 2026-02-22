<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoCertificatesTemplatesRepository.php';
require_once __DIR__ . '/../validators/CertificatesTemplatesValidator.php';

final class CertificatesTemplatesService
{
    private PdoCertificatesTemplatesRepository $repo;

    public function __construct(PdoCertificatesTemplatesRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        $items = $this->repo->all($filters, $limit, $offset, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) throw new RuntimeException('Template not found');
        return $row;
    }

    public function create(array $data): array
    {
        $errors = CertificatesTemplatesValidator::validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // ensure unique code+language
        $existing = $this->repo->findByCode($data['code'], $data['language_code'] ?? null);
        if ($existing) {
            throw new InvalidArgumentException('Template code already exists for this language');
        }

        $id = $this->repo->save($data);
        return $this->get((int)$id);
    }

    public function update(array $data): array
    {
        $errors = CertificatesTemplatesValidator::validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // check exists
        $existing = $this->repo->find((int)$data['id']);
        if (!$existing) throw new RuntimeException('Template not found');

        // if code or language changed ensure uniqueness
        if ((isset($data['code']) && $data['code'] !== $existing['code']) || (isset($data['language_code']) && $data['language_code'] !== $existing['language_code'])) {
            $conflict = $this->repo->findByCode($data['code'] ?? $existing['code'], $data['language_code'] ?? $existing['language_code']);
            if ($conflict && (int)$conflict['id'] !== (int)$existing['id']) {
                throw new InvalidArgumentException('Template code already exists for this language');
            }
        }

        $id = $this->repo->save($data);
        return $this->get((int)$id);
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete template');
        }
    }
}