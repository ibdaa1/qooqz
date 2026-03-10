<?php
/**
 * TORO — v1/modules/Payments/Services/PaymentsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class PaymentsService
{
    public function __construct(private readonly PaymentsRepositoryInterface $repo) {}

    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getById(int $id): array
    {
        $payment = $this->repo->findById($id);
        if (!$payment) throw new NotFoundException("الدفعة #{$id} غير موجودة");
        return $this->withRefunds($payment);
    }

    public function getByOrderId(int $orderId): array
    {
        return $this->repo->findByOrderId($orderId);
    }

    public function create(CreatePaymentDTO $dto): array
    {
        PaymentsValidator::create([
            'order_id' => $dto->orderId,
            'method'   => $dto->method,
            'amount'   => $dto->amount,
        ]);

        $id = $this->repo->create([
            'order_id'       => $dto->orderId,
            'method'         => $dto->method,
            'amount'         => $dto->amount,
            'currency'       => $dto->currency,
            'gateway'        => $dto->gateway,
            'gateway_txn_id' => $dto->gatewayTxnId,
            'gateway_ref'    => $dto->gatewayRef,
        ]);

        return $this->getById($id);
    }

    public function updateStatus(int $id, string $status): array
    {
        $allowed = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
        if (!in_array($status, $allowed)) {
            throw new ValidationException(['status' => 'حالة الدفع غير صالحة']);
        }

        $payment = $this->repo->findById($id);
        if (!$payment) throw new NotFoundException("الدفعة #{$id} غير موجودة");

        $paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
        $this->repo->updateStatus($id, $status, $paidAt);

        return $this->getById($id);
    }

    // ── Refunds ────────────────────────────────────────────────
    public function getRefunds(int $paymentId): array
    {
        $payment = $this->repo->findById($paymentId);
        if (!$payment) throw new NotFoundException("الدفعة #{$paymentId} غير موجودة");
        return $this->repo->getRefunds($paymentId);
    }

    public function createRefund(int $paymentId, array $raw): array
    {
        PaymentsValidator::refund($raw);

        $payment = $this->repo->findById($paymentId);
        if (!$payment) throw new NotFoundException("الدفعة #{$paymentId} غير موجودة");

        $id = $this->repo->createRefund($paymentId, [
            'amount' => (float)$raw['amount'],
            'reason' => $raw['reason'] ?? null,
        ]);

        return $this->repo->findRefundById($id) ?? [];
    }

    public function processRefund(int $refundId, string $status, int $processedBy): array
    {
        $allowed = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowed)) {
            throw new ValidationException(['status' => 'حالة الاسترداد غير صالحة']);
        }

        $refund = $this->repo->findRefundById($refundId);
        if (!$refund) throw new NotFoundException("طلب الاسترداد #{$refundId} غير موجود");

        $this->repo->updateRefund($refundId, $status, $processedBy);
        return $this->repo->findRefundById($refundId) ?? [];
    }

    // ── Private ────────────────────────────────────────────────
    private function withRefunds(array $payment): array
    {
        $payment['refunds'] = $this->repo->getRefunds((int)$payment['id']);
        return $payment;
    }
}
