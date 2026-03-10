<?php
/**
 * TORO — v1/modules/OrderShippingAddresses/Services/OrderShippingAddressesService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class OrderShippingAddressesService
{
    public function __construct(private readonly OrderShippingAddressesRepositoryInterface $repo) {}

    public function getByOrderId(int $orderId): array
    {
        $row = $this->repo->findByOrderId($orderId);
        if (!$row) throw new NotFoundException("لا يوجد عنوان شحن للطلب #{$orderId}");
        return $row;
    }

    public function upsert(int $orderId, array $raw): array
    {
        $this->validate($raw);

        $id = $this->repo->upsert($orderId, [
            'full_name'     => trim($raw['full_name']),
            'phone'         => trim($raw['phone']),
            'country_code'  => strtoupper(trim($raw['country_code'])),
            'city'          => trim($raw['city']),
            'district'      => isset($raw['district'])      ? trim($raw['district'])      : null,
            'address_line1' => trim($raw['address_line1']),
            'address_line2' => isset($raw['address_line2']) ? trim($raw['address_line2']) : null,
            'postal_code'   => isset($raw['postal_code'])   ? trim($raw['postal_code'])   : null,
        ]);

        return $this->getByOrderId($orderId);
    }

    private function validate(array $data): void
    {
        $errors = [];

        if (empty($data['full_name']))     $errors['full_name']     = 'الاسم الكامل مطلوب';
        if (empty($data['phone']))         $errors['phone']         = 'رقم الهاتف مطلوب';
        if (empty($data['country_code']))  $errors['country_code']  = 'رمز الدولة مطلوب';
        if (empty($data['city']))          $errors['city']          = 'المدينة مطلوبة';
        if (empty($data['address_line1'])) $errors['address_line1'] = 'العنوان الأول مطلوب';

        if (!empty($data['country_code']) && strlen(trim($data['country_code'])) !== 2) {
            $errors['country_code'] = 'رمز الدولة يجب أن يكون حرفين';
        }

        if ($errors) throw new ValidationException($errors);
    }
}
