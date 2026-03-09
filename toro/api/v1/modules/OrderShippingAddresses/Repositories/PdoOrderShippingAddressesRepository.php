<?php
/**
 * TORO — v1/modules/OrderShippingAddresses/Repositories/PdoOrderShippingAddressesRepository.php
 */
declare(strict_types=1);

final class PdoOrderShippingAddressesRepository implements OrderShippingAddressesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByOrderId(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_shipping_addresses WHERE order_id = :order_id LIMIT 1'
        );
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsert(int $orderId, array $data): int
    {
        $existing = $this->findByOrderId($orderId);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE order_shipping_addresses
                 SET full_name    = :full_name,
                     phone        = :phone,
                     country_code = :country_code,
                     city         = :city,
                     district     = :district,
                     address_line1= :address_line1,
                     address_line2= :address_line2,
                     postal_code  = :postal_code
                 WHERE order_id = :order_id'
            );
            $stmt->execute([
                ':full_name'     => $data['full_name'],
                ':phone'         => $data['phone'],
                ':country_code'  => $data['country_code'],
                ':city'          => $data['city'],
                ':district'      => $data['district'] ?? null,
                ':address_line1' => $data['address_line1'],
                ':address_line2' => $data['address_line2'] ?? null,
                ':postal_code'   => $data['postal_code'] ?? null,
                ':order_id'      => $orderId,
            ]);
            return (int)$existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO order_shipping_addresses
             (order_id, full_name, phone, country_code, city, district, address_line1, address_line2, postal_code)
             VALUES
             (:order_id, :full_name, :phone, :country_code, :city, :district, :address_line1, :address_line2, :postal_code)'
        );
        $stmt->execute([
            ':order_id'      => $orderId,
            ':full_name'     => $data['full_name'],
            ':phone'         => $data['phone'],
            ':country_code'  => $data['country_code'],
            ':city'          => $data['city'],
            ':district'      => $data['district'] ?? null,
            ':address_line1' => $data['address_line1'],
            ':address_line2' => $data['address_line2'] ?? null,
            ':postal_code'   => $data['postal_code'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $orderId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM order_shipping_addresses WHERE order_id = :order_id'
        );
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->rowCount() > 0;
    }
}
