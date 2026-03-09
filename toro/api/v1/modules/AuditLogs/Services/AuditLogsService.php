<?php
/**
 * TORO — v1/modules/AuditLogs/Services/AuditLogsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\NotFoundException;

final class AuditLogsService
{
    public function __construct(private readonly AuditLogsRepositoryInterface $repo) {}

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
        $log = $this->repo->findById($id);
        if (!$log) throw new NotFoundException("سجل التدقيق #{$id} غير موجود");
        return $log;
    }

    public function create(array $data): array
    {
        $id = $this->repo->create([
            'user_id'    => isset($data['user_id'])   ? (int)$data['user_id']   : null,
            'action'     => $data['action']            ?? 'unknown',
            'entity'     => $data['entity']            ?? null,
            'entity_id'  => isset($data['entity_id']) ? (int)$data['entity_id'] : null,
            'old_values' => $data['old_values']        ?? null,
            'new_values' => $data['new_values']        ?? null,
            'ip_address' => $data['ip_address']        ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $data['user_agent']        ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
        ]);

        return $this->getById($id);
    }
}
