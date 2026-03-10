<?php
/**
 * TORO — v1/modules/Payments/Contracts/PaymentsRepositoryInterface.php
 */
declare(strict_types=1);

interface PaymentsRepositoryInterface
{
    // Payments
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByOrderId(int $orderId): array;
    public function create(array $data): int;
    public function updateStatus(int $id, string $status, ?string $paidAt): bool;

    // Refunds
    public function getRefunds(int $paymentId): array;
    public function createRefund(int $paymentId, array $data): int;
    public function updateRefund(int $refundId, string $status, ?int $processedBy): bool;
    public function findRefundById(int $refundId): ?array;
}
