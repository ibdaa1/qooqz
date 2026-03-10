<?php
/**
 * TORO — v1/modules/AuditLogs/Contracts/AuditLogsRepositoryInterface.php
 */
declare(strict_types=1);

interface AuditLogsRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function create(array $data): int;
}
