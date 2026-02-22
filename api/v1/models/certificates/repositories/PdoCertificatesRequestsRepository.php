<?php
declare(strict_types=1);

final class PdoCertificatesRequestsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'tenant_id', 'entity_id', 'importer_country_id',
        'certificate_type', 'operation_type', 'issue_date', 'status',
        'shipment_condition', 'created_at', 'updated_at', 'certificate_id',
        'certificate_edition_id', 'auditor_user_id', 'payment_status',
    ];

    // NOTE: 'status' intentionally excluded — handled in buildWhere() with IN/NOT IN support
    private const FILTERABLE_COLUMNS = [
        'tenant_id', 'entity_id', 'importer_country_id',
        'certificate_type', 'operation_type',
        'shipment_condition', 'certificate_id', 'certificate_edition_id',
        'auditor_user_id', 'payment_status',
    ];

    private const VALID_STATUSES = [
        'draft', 'under_review', 'payment_pending', 'approved', 'rejected', 'issued',
    ];

    private const TABLE_COLUMNS = [
        'tenant_id', 'entity_id', 'importer_country_id', 'certificate_type',
        'operation_type', 'description', 'importer_name', 'importer_address',
        'issue_date', 'transport_method', 'notes', 'status', 'issued_id',
        'shipment_condition', 'certificate_id', 'certificate_edition_id',
        'auditor_user_id', 'payment_status',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── LIST ──────────────────────────────────────────────────────────────

    public function all(
        int    $tenantId,
        ?int   $limit    = null,
        ?int   $offset   = null,
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC'
    ): array {
        [$where, $params] = $this->buildWhere($tenantId, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT cr.*,
                   CASE cr.shipment_condition
                       WHEN 1 THEN 'Chilled'
                       WHEN 2 THEN 'Dry'
                       WHEN 3 THEN 'Frozen'
                       ELSE NULL
                   END AS shipment_condition_label,
                   c_imp.name AS importer_country
            FROM certificates_requests cr
            LEFT JOIN countries c_imp ON c_imp.id = cr.importer_country_id
            {$where}
            ORDER BY cr.{$orderBy} {$orderDir}
        ";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }
        if ($offset !== null) {
            $sql .= ' OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── COUNT ─────────────────────────────────────────────────────────────

    public function count(int $tenantId, array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($tenantId, $filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM certificates_requests cr {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── FIND ONE ──────────────────────────────────────────────────────────

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*,
                   CASE cr.shipment_condition
                       WHEN 1 THEN 'Chilled'
                       WHEN 2 THEN 'Dry'
                       WHEN 3 THEN 'Frozen'
                       ELSE NULL
                   END AS shipment_condition_label,
                   c_imp.name AS importer_country
            FROM certificates_requests cr
            LEFT JOIN countries c_imp ON c_imp.id = cr.importer_country_id
            WHERE cr.tenant_id = :tenant_id AND cr.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── SAVE (INSERT or UPDATE) ───────────────────────────────────────────

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $values = [
            'tenant_id'              => $tenantId,
            'entity_id'              => isset($data['entity_id'])              && $data['entity_id']              !== '' ? (int)$data['entity_id']              : null,
            'importer_country_id'    => isset($data['importer_country_id'])    && $data['importer_country_id']    !== '' ? (int)$data['importer_country_id']    : 1,
            'certificate_type'       => $data['certificate_type']       ?? 'gcc',
            'operation_type'         => $data['operation_type']         ?? 'export',
            'description'            => $data['description']            ?? null,
            'importer_name'          => $data['importer_name']          ?? null,
            'importer_address'       => $data['importer_address']       ?? null,
            'issue_date'             => ($data['issue_date'] ?? null) ?: null,
            'transport_method'       => $data['transport_method']       ?? 'sea',
            'notes'                  => $data['notes']                  ?? null,
            'status'                 => $data['status']                 ?? 'draft',
            'issued_id'              => isset($data['issued_id'])              && $data['issued_id']              !== '' ? (int)$data['issued_id']              : null,
            'shipment_condition'     => $this->resolveShipmentCondition($data['shipment_condition'] ?? null),
            'certificate_id'         => isset($data['certificate_id'])         && $data['certificate_id']         !== '' ? (int)$data['certificate_id']         : 1,
            'certificate_edition_id' => isset($data['certificate_edition_id']) && $data['certificate_edition_id'] !== '' ? (int)$data['certificate_edition_id'] : null,
            'auditor_user_id'        => isset($data['auditor_user_id'])        && $data['auditor_user_id']        !== '' ? (int)$data['auditor_user_id']        : null,
            'payment_status'         => ($data['payment_status'] ?? null) ?: null,
        ];

        if ($isUpdate) {
            $setParts = [];
            $params   = [':_id' => (int)$data['id'], ':_tenant_id' => $tenantId];

            foreach (self::TABLE_COLUMNS as $col) {
                if ($col === 'tenant_id') {
                    $setParts[]            = "{$col} = :set_{$col}";
                    $params[":set_{$col}"] = $values[$col];
                    continue;
                }
                if (!array_key_exists($col, $data) && $col !== 'shipment_condition') {
                    continue;
                }
                $setParts[]            = "{$col} = :set_{$col}";
                $params[":set_{$col}"] = $values[$col];
            }

            if (empty($setParts)) {
                throw new \InvalidArgumentException('No fields to update.');
            }

            $sql  = 'UPDATE certificates_requests SET ' . implode(', ', $setParts) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :_id AND tenant_id = :_tenant_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $placeholders = array_map(fn(string $c) => ":ins_{$c}", self::TABLE_COLUMNS);
        $params       = [];
        foreach (self::TABLE_COLUMNS as $col) {
            $params[":ins_{$col}"] = $values[$col];
        }

        $sql  = sprintf('INSERT INTO certificates_requests (%s) VALUES (%s)', implode(', ', self::TABLE_COLUMNS), implode(', ', $placeholders));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ── DELETE ────────────────────────────────────────────────────────────

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM certificates_requests WHERE tenant_id = :tenant_id AND id = :id');
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // ── WHERE BUILDER ─────────────────────────────────────────────────────

    /**
     * Builds WHERE clause with full server-side status filtering:
     *
     *   ?status=approved               → WHERE status = 'approved'
     *   ?status=draft,under_review     → WHERE status IN ('draft','under_review')
     *   ?status_exclude=approved,issued → WHERE status NOT IN ('approved','issued')
     *
     * No client-side filtering needed — DB does the work efficiently via indexed column.
     */
    private function buildWhere(int $tenantId, array $filters): array
    {
        $where  = 'WHERE cr.tenant_id = :tenant_id';
        $params = [':tenant_id' => $tenantId];

        // Single-value columns
        foreach (self::FILTERABLE_COLUMNS as $col) {
            $val = $filters[$col] ?? null;
            if ($val === null || $val === '') continue;
            $where               .= " AND cr.{$col} = :f_{$col}";
            $params[":f_{$col}"] = is_numeric($val) ? (int)$val : $val;
        }

        // ── status IN filter ──────────────────────────────────────────────
        $statusIn = $filters['status'] ?? null;
        if ($statusIn !== null && $statusIn !== '') {
            $list  = is_array($statusIn)
                ? $statusIn
                : array_map('trim', explode(',', (string)$statusIn));
            $valid = array_values(array_filter($list, fn($s) => in_array($s, self::VALID_STATUSES, true)));

            if (count($valid) === 1) {
                $where              .= ' AND cr.status = :f_status';
                $params[':f_status'] = $valid[0];
            } elseif (count($valid) > 1) {
                $keys = [];
                foreach ($valid as $i => $s) {
                    $k          = ":f_s_in_{$i}";
                    $keys[]     = $k;
                    $params[$k] = $s;
                }
                $where .= ' AND cr.status IN (' . implode(', ', $keys) . ')';
            }
        }

        // ── status NOT IN filter ──────────────────────────────────────────
        $statusEx = $filters['status_exclude'] ?? null;
        if ($statusEx !== null && $statusEx !== '') {
            $list  = is_array($statusEx)
                ? $statusEx
                : array_map('trim', explode(',', (string)$statusEx));
            $valid = array_values(array_filter($list, fn($s) => in_array($s, self::VALID_STATUSES, true)));

            if (!empty($valid)) {
                $keys = [];
                foreach ($valid as $i => $s) {
                    $k          = ":f_s_ex_{$i}";
                    $keys[]     = $k;
                    $params[$k] = $s;
                }
                $where .= ' AND cr.status NOT IN (' . implode(', ', $keys) . ')';
            }
        }

        // ── importer_name search ──────────────────────────────────────────
        if (!empty($filters['search'])) {
            $where              .= ' AND cr.importer_name LIKE :f_search';
            $params[':f_search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['importer_name'])) {
            $where                      .= ' AND cr.importer_name LIKE :f_importer_name';
            $params[':f_importer_name'] = '%' . $filters['importer_name'] . '%';
        }

        // ── Date range ────────────────────────────────────────────────────
        if (!empty($filters['issue_date_from'])) {
            $where                       .= ' AND cr.issue_date >= :f_issue_date_from';
            $params[':f_issue_date_from'] = $filters['issue_date_from'];
        }
        if (!empty($filters['issue_date_to'])) {
            $where                     .= ' AND cr.issue_date <= :f_issue_date_to';
            $params[':f_issue_date_to'] = $filters['issue_date_to'];
        }

        return [$where, $params];
    }

    private function resolveShipmentCondition(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value) && in_array((int)$value, [1, 2, 3], true)) return (int)$value;
        return ['chilled' => 1, 'dry' => 2, 'frozen' => 3][strtolower((string)$value)] ?? null;
    }
}