<?php
/**
 * TORO — v1/modules/UserAddresses/Services/UserAddressesService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class UserAddressesService
{
    public function __construct(private readonly UserAddressesRepositoryInterface $repo) {}

    // ── List ───────────────────────────────────────────────────
    public function listByUser(int $userId): array
    {
        return $this->repo->findByUserId($userId);
    }

    // ── Get one ────────────────────────────────────────────────
    public function getById(int $id): array
    {
        $address = $this->repo->findById($id);
        if (!$address) throw new NotFoundException("العنوان #{$id} غير موجود");
        return $address;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): array
    {
        UserAddressesValidator::create($data);

        if (!empty($data['is_default'])) {
            $this->repo->clearDefault((int)$data['user_id']);
        }

        $id = $this->repo->create([
            'user_id'       => (int)$data['user_id'],
            'label'         => trim($data['label'] ?? 'Home'),
            'full_name'     => trim($data['full_name']),
            'phone'         => trim($data['phone']),
            'country_id'    => (int)$data['country_id'],
            'city_id'       => (int)$data['city_id'],
            'district'      => isset($data['district'])      ? trim($data['district'])      : null,
            'address_line1' => trim($data['address_line1']),
            'address_line2' => isset($data['address_line2']) ? trim($data['address_line2']) : null,
            'postal_code'   => isset($data['postal_code'])   ? trim($data['postal_code'])   : null,
            'is_default'    => (int)!empty($data['is_default']),
        ]);

        return $this->getById($id);
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): array
    {
        $address = $this->getById($id);
        UserAddressesValidator::update($data);

        $payload = [];
        if (array_key_exists('label',         $data)) $payload['label']         = trim($data['label']);
        if (array_key_exists('full_name',     $data)) $payload['full_name']     = trim($data['full_name']);
        if (array_key_exists('phone',         $data)) $payload['phone']         = trim($data['phone']);
        if (array_key_exists('country_id',    $data)) $payload['country_id']    = (int)$data['country_id'];
        if (array_key_exists('city_id',       $data)) $payload['city_id']       = (int)$data['city_id'];
        if (array_key_exists('district',      $data)) $payload['district']      = $data['district'] ? trim($data['district']) : null;
        if (array_key_exists('address_line1', $data)) $payload['address_line1'] = trim($data['address_line1']);
        if (array_key_exists('address_line2', $data)) $payload['address_line2'] = $data['address_line2'] ? trim($data['address_line2']) : null;
        if (array_key_exists('postal_code',   $data)) $payload['postal_code']   = $data['postal_code'] ? trim($data['postal_code']) : null;

        if (array_key_exists('is_default', $data) && (int)(bool)$data['is_default'] === 1) {
            $this->repo->clearDefault((int)$address['user_id']);
            $payload['is_default'] = 1;
        }

        $this->repo->update($id, $payload);
        return $this->getById($id);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $this->getById($id);
        return $this->repo->delete($id);
    }

    // ── Set default ────────────────────────────────────────────
    public function setDefault(int $id, int $userId): array
    {
        $this->getById($id);
        $this->repo->clearDefault($userId);
        $this->repo->setDefault($id, $userId);
        return $this->getById($id);
    }
}
