<?php
declare(strict_types=1);

final class CertificatesRequestItemsService
{
    private PdoCertificatesRequestItemsRepository $repo;
    private PDO                                    $pdo;
    private CertificatesLogsService                $logsService;

    public function __construct(
        PdoCertificatesRequestItemsRepository $repo,
        PDO $pdo,
        CertificatesLogsService $logsService
    ) {
        $this->repo        = $repo;
        $this->pdo         = $pdo;
        $this->logsService = $logsService;
    }

    // ================================
    // List
    // ================================
    public function list(
        int $tenantId,
        array $filters = [],
        ?int $limit = null,
        ?int $offset = null,
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        // أمان: إذا أُرسل request_id تحقق أنه ينتمي لنفس الـ tenant
        if (!empty($filters['request_id'])) {
            $this->assertRequestBelongsToTenant((int)$filters['request_id'], $tenantId);
        }

        $items = $this->repo->all($filters, $limit, $offset, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    // ================================
    // Get single
    // ================================
    public function get(int $tenantId, int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Item not found.');
        }
        $this->assertRequestBelongsToTenant((int)$row['request_id'], $tenantId);
        return $row;
    }

    // ================================
    // Create
    // ================================
    public function create(int $tenantId, array $data, int $userId): array
    {
        $errors = CertificatesRequestItemsValidator::validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->assertRequestBelongsToTenant((int)$data['request_id'], $tenantId);

        $id = $this->repo->save($data);

        // تسجيل في certificates_logs عبر /api/certificates_logs
        $this->log(
            (int)$data['request_id'],
            $userId,
            'create',
            'Request item created. Item ID: ' . $id
            . ', product_id: ' . ($data['product_id'] ?? '-')
            . ', quantity: '   . ($data['quantity']   ?? '-')
        );

        return $this->get($tenantId, $id);
    }

    // ================================
    // Update
    // ================================
    public function update(int $tenantId, array $data, int $userId): array
    {
        $errors = CertificatesRequestItemsValidator::validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $existing = $this->repo->find((int)$data['id']);
        if (!$existing) {
            throw new RuntimeException('Item not found.');
        }
        $this->assertRequestBelongsToTenant((int)$existing['request_id'], $tenantId);

        $this->repo->save($data);

        $changes = $this->buildChangeSummary($existing, $data);
        $this->log(
            (int)$existing['request_id'],
            $userId,
            'update',
            'Request item updated. Item ID: ' . $data['id'] . ($changes ? ' | Changes: ' . $changes : '')
        );

        return $this->get($tenantId, (int)$data['id']);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id, int $userId): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Item not found.');
        }
        $this->assertRequestBelongsToTenant((int)$existing['request_id'], $tenantId);

        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete item.');
        }

        $this->log(
            (int)$existing['request_id'],
            $userId,
            'update',
            'Request item deleted. Item ID: ' . $id
            . ', product_id: ' . ($existing['product_id'] ?? '-')
        );
    }

    // ================================
    // Helper: تحقق من ملكية الـ request للـ tenant
    // ================================
    private function assertRequestBelongsToTenant(int $requestId, int $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT tenant_id FROM certificates_requests WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $requestId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$r || (int)$r['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Unauthorized: request not found or belongs to another tenant.');
        }
    }

    // ================================
    // Helper: تسجيل في certificates_logs عبر الـ LogsService
    // ================================
    private function log(int $requestId, int $userId, string $actionType, string $notes = ''): void
    {
        try {
            $this->logsService->create($requestId, $userId, $actionType, $notes ?: null);
        } catch (Throwable $e) {
            // لا نوقف العملية بسبب فشل اللوج
            error_log('[cert_items.log_error] ' . $e->getMessage());
        }
    }

    // ================================
    // Helper: مقارنة القيم القديمة بالجديدة
    // ================================
    private function buildChangeSummary(array $old, array $new): string
    {
        $tracked = [
            'product_id', 'quantity', 'net_weight',
            'weight_unit_id', 'production_date', 'expiry_date', 'notes'
        ];
        $parts = [];
        foreach ($tracked as $field) {
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            if (array_key_exists($field, $new) && (string)$oldVal !== (string)$newVal) {
                $parts[] = "{$field}: {$oldVal} → {$newVal}";
            }
        }
        return implode(', ', $parts);
    }
}