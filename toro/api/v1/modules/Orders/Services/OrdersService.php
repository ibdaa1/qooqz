<?php
/**
 * TORO — v1/modules/Orders/Services/OrdersService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class OrdersService
{
    public function __construct(private readonly OrdersRepositoryInterface $repo) {}

    // ── List ───────────────────────────────────────────────────
    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ── Get one ────────────────────────────────────────────────
    public function getById(int $id): array
    {
        $order = $this->repo->findById($id);
        if (!$order) throw new NotFoundException("الطلب #{$id} غير موجود");
        return $this->withDetails($order);
    }

    public function getByNumber(string $orderNumber): array
    {
        $order = $this->repo->findByNumber($orderNumber);
        if (!$order) throw new NotFoundException("الطلب '{$orderNumber}' غير موجود");
        return $this->withDetails($order);
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(CreateOrderDTO $dto, array $meta = []): array
    {
        OrdersValidator::create(['items' => $dto->items]);

        $orderNumber = $this->repo->generateOrderNumber();

        $orderId = $this->repo->create([
            'order_number'  => $orderNumber,
            'user_id'       => $dto->userId,
            'address_id'    => $dto->addressId,
            'status'        => 'pending',
            'subtotal'      => $dto->subtotal,
            'discount'      => $dto->discount,
            'shipping_cost' => $dto->shippingCost,
            'tax'           => $dto->tax,
            'total'         => $dto->total(),
            'currency'      => $dto->currency,
            'coupon_id'     => $dto->couponId,
            'notes'         => $dto->notes,
            'language_id'   => $dto->languageId,
            'ip_address'    => $meta['ip_address'] ?? null,
            'user_agent'    => $meta['user_agent'] ?? null,
        ]);

        foreach ($dto->items as $item) {
            $this->repo->addItem($orderId, $item);
        }

        $this->repo->addStatusHistory($orderId, 'pending', null, $dto->userId);

        if ($dto->userId !== null) {
            AuditLogger::log('order_created', $dto->userId, 'orders', $orderId);
        }

        return $this->withDetails($this->repo->findById($orderId));
    }

    // ── Update status ──────────────────────────────────────────
    public function updateStatus(int $id, string $status, ?string $note, int $actorId): array
    {
        OrdersValidator::validateStatus($status);

        $order = $this->repo->findById($id);
        if (!$order) throw new NotFoundException("الطلب #{$id} غير موجود");

        $this->repo->updateStatus($id, $status);
        $this->repo->addStatusHistory($id, $status, $note, $actorId);

        AuditLogger::log('order_status_updated', $actorId, 'orders', $id);

        return $this->withDetails($this->repo->findById($id));
    }

    // ── Status history ─────────────────────────────────────────
    public function getStatusHistory(int $id): array
    {
        $order = $this->repo->findById($id);
        if (!$order) throw new NotFoundException("الطلب #{$id} غير موجود");
        return $this->repo->getStatusHistory($id);
    }

    // ── Helpers ────────────────────────────────────────────────
    private function withDetails(?array $order): array
    {
        if (!$order) return [];
        $order['items']          = $this->repo->getItems((int)$order['id']);
        $order['status_history'] = $this->repo->getStatusHistory((int)$order['id']);
        return $order;
    }
}
