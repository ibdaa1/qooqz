<?php
/**
 * TORO — v1/modules/Carts/Services/CartsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class CartsService
{
    public function __construct(private readonly CartsRepositoryInterface $repo) {}

    // ── Get or create a cart ───────────────────────────────────
    public function getOrCreate(?int $userId, ?string $sessionKey): array
    {
        if ($userId) {
            $cart = $this->repo->findCartByUser($userId);
        } elseif ($sessionKey) {
            $cart = $this->repo->findCartBySession($sessionKey);
        } else {
            throw new ValidationException('يجب توفير user_id أو session_key', []);
        }

        if (!$cart) {
            $cartId = $this->repo->createCart([
                'user_id'     => $userId,
                'session_key' => $sessionKey,
            ]);
            $cart = $this->repo->findCartById($cartId);
        }

        return $this->withItems($cart);
    }

    // ── Get by ID ──────────────────────────────────────────────
    public function getById(int $id): array
    {
        $cart = $this->repo->findCartById($id);
        if (!$cart) throw new NotFoundException("السلة #{$id} غير موجودة");
        return $this->withItems($cart);
    }

    // ── Add / update item ──────────────────────────────────────
    public function addItem(int $cartId, array $data): array
    {
        $cart = $this->repo->findCartById($cartId);
        if (!$cart) throw new NotFoundException("السلة #{$cartId} غير موجودة");

        if (empty($data['product_id']) || empty($data['unit_price'])) {
            throw new ValidationException('product_id و unit_price مطلوبان', []);
        }

        $qty = max(1, (int)($data['qty'] ?? 1));
        $existing = $this->repo->findItem($cartId, (int)$data['product_id'], $data['variant_id'] ?? null);

        if ($existing) {
            $this->repo->updateItem((int)$existing['id'], ['qty' => (int)$existing['qty'] + $qty]);
        } else {
            $this->repo->addItem($cartId, array_merge($data, ['qty' => $qty]));
        }

        return $this->withItems($this->repo->findCartById($cartId));
    }

    // ── Update item qty ────────────────────────────────────────
    public function updateItem(int $cartId, int $itemId, int $qty): array
    {
        $cart = $this->repo->findCartById($cartId);
        if (!$cart) throw new NotFoundException("السلة #{$cartId} غير موجودة");

        if ($qty <= 0) {
            $this->repo->removeItem($itemId);
        } else {
            $this->repo->updateItem($itemId, ['qty' => $qty]);
        }

        return $this->withItems($this->repo->findCartById($cartId));
    }

    // ── Remove item ────────────────────────────────────────────
    public function removeItem(int $cartId, int $itemId): array
    {
        $cart = $this->repo->findCartById($cartId);
        if (!$cart) throw new NotFoundException("السلة #{$cartId} غير موجودة");

        $this->repo->removeItem($itemId);
        return $this->withItems($this->repo->findCartById($cartId));
    }

    // ── Apply coupon ───────────────────────────────────────────
    public function applyCoupon(int $cartId, ?int $couponId): array
    {
        $cart = $this->repo->findCartById($cartId);
        if (!$cart) throw new NotFoundException("السلة #{$cartId} غير موجودة");

        $this->repo->updateCart($cartId, ['coupon_id' => $couponId]);
        return $this->withItems($this->repo->findCartById($cartId));
    }

    // ── Clear ──────────────────────────────────────────────────
    public function clear(int $cartId): void
    {
        $cart = $this->repo->findCartById($cartId);
        if (!$cart) throw new NotFoundException("السلة #{$cartId} غير موجودة");
        $this->repo->clearItems($cartId);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $cartId): void
    {
        $this->repo->deleteCart($cartId);
    }

    // ── Helpers ────────────────────────────────────────────────
    private function withItems(?array $cart): array
    {
        if (!$cart) return [];
        $cart['items'] = $this->repo->getItems((int)$cart['id']);
        return $cart;
    }
}
