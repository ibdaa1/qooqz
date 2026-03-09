<?php
/**
 * TORO — v1/modules/UserAddresses/Repositories/PdoUserAddressesRepository.php
 *
 * user_addresses is linked to the existing `countries` and `cities` tables:
 *   country_id  INT FK → countries.id
 *   city_id     INT FK → cities.id
 *
 * The old free-text `country_code` / `city` columns are replaced.
 */
declare(strict_types=1);

final class PdoUserAddressesRepository implements UserAddressesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ua.*,
                    co.name          AS country_name,
                    co.iso2          AS country_iso2,
                    co.currency_code AS country_currency,
                    ci.name          AS city_name,
                    ci.state         AS city_state
             FROM user_addresses ua
             LEFT JOIN countries co ON co.id = ua.country_id
             LEFT JOIN cities    ci ON ci.id = ua.city_id
             WHERE ua.user_id = :user_id
             ORDER BY ua.is_default DESC, ua.id ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    // ── Find ───────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ua.*,
                    co.name          AS country_name,
                    co.iso2          AS country_iso2,
                    co.currency_code AS country_currency,
                    ci.name          AS city_name,
                    ci.state         AS city_state
             FROM user_addresses ua
             LEFT JOIN countries co ON co.id = ua.country_id
             LEFT JOIN cities    ci ON ci.id = ua.city_id
             WHERE ua.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_addresses
                (user_id, label, full_name, phone, country_id, city_id,
                 district, address_line1, address_line2, postal_code, is_default)
             VALUES
                (:user_id, :label, :full_name, :phone, :country_id, :city_id,
                 :district, :address_line1, :address_line2, :postal_code, :is_default)'
        );
        $stmt->execute([
            ':user_id'       => (int)$data['user_id'],
            ':label'         => $data['label']         ?? 'Home',
            ':full_name'     => $data['full_name'],
            ':phone'         => $data['phone'],
            ':country_id'    => (int)$data['country_id'],
            ':city_id'       => (int)$data['city_id'],
            ':district'      => $data['district']      ?? null,
            ':address_line1' => $data['address_line1'],
            ':address_line2' => $data['address_line2'] ?? null,
            ':postal_code'   => $data['postal_code']   ?? null,
            ':is_default'    => (int)($data['is_default'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'label', 'full_name', 'phone', 'country_id', 'city_id',
            'district', 'address_line1', 'address_line2', 'postal_code', 'is_default',
        ];
        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $sets[] = "{$field} = :{$field}";
            $params[":{$field}"] = $data[$field];
        }

        if (empty($sets)) return false;

        $stmt = $this->pdo->prepare('UPDATE user_addresses SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_addresses WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Default management ─────────────────────────────────────
    public function setDefault(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function clearDefault(int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return true;
    }
}
