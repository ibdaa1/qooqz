<?php
/**
 * TORO — v1/modules/ProductAttributeValues/Repositories/PdoProductAttributeValuesRepository.php
 */
declare(strict_types=1);

final class PdoProductAttributeValuesRepository implements ProductAttributeValuesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── All values for a product ───────────────────────────────
    public function findByProduct(int $productId, ?string $lang = null): array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                pav.id, pav.product_id, pav.value_id,
                av.slug AS value_slug, av.color_hex, av.attribute_id,
                a.slug  AS attribute_slug,
                avt.name AS value_name,
                at.name  AS attribute_name
            FROM product_attribute_values pav
            JOIN attribute_values av ON av.id = pav.value_id
            JOIN attributes a        ON a.id  = av.attribute_id
            LEFT JOIN attribute_value_translations avt
                ON avt.value_id = av.id
                AND avt.language_id = COALESCE(:lang_id,
                    (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN attribute_translations at
                ON at.attribute_id = a.id
                AND at.language_id = COALESCE(:lang_id2,
                    (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            WHERE pav.product_id = :product_id
            ORDER BY a.sort_order, av.sort_order
        ");
        $stmt->bindValue(':lang_id',    $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':lang_id2',   $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Attach a single value ──────────────────────────────────
    public function attach(int $productId, int $valueId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO product_attribute_values (product_id, value_id)
            VALUES (:product_id, :value_id)
        ");
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->bindValue(':value_id',   $valueId,   \PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    // ── Detach a single value ──────────────────────────────────
    public function detach(int $productId, int $valueId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM product_attribute_values
            WHERE product_id = :product_id AND value_id = :value_id
        ");
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->bindValue(':value_id',   $valueId,   \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // ── Sync (replace all values for a product) ───────────────
    public function syncForProduct(int $productId, array $valueIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare(
                'DELETE FROM product_attribute_values WHERE product_id = :pid'
            );
            $del->bindValue(':pid', $productId, \PDO::PARAM_INT);
            $del->execute();

            if (!empty($valueIds)) {
                $ins = $this->pdo->prepare(
                    'INSERT IGNORE INTO product_attribute_values (product_id, value_id) VALUES (:pid, :vid)'
                );
                foreach ($valueIds as $vid) {
                    $ins->bindValue(':pid', $productId, \PDO::PARAM_INT);
                    $ins->bindValue(':vid', (int)$vid,  \PDO::PARAM_INT);
                    $ins->execute();
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Helpers ────────────────────────────────────────────────
    private function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM languages WHERE code = :code LIMIT 1');
        $stmt->bindValue(':code', $code);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}
