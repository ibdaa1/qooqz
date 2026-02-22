<?php

declare(strict_types=1);



/**
 * خدمة إدارة نسخ الشهادات (Certificates Versions)
 * تتعامل مع العمليات الأساسية: قائمة، عرض، إنشاء، تحديث، حذف
 * مع تسجيل كل عملية في سجل الـ logs
 */
final class CertificatesVersionsService
{
    private PdoCertificatesVersionsRepository $repo;
    private CertificatesLogsRepository $logs;

    public function __construct(
        PdoCertificatesVersionsRepository $repo,
        CertificatesLogsRepository $logs
    ) {
        $this->repo = $repo;
        $this->logs = $logs;
    }

    /**
     * جلب قائمة النسخ مع التصفية والترقيم والترتيب
     *
     * @param int      $tenantId   معرف المستأجر (Tenant)
     * @param int|null $limit      عدد العناصر في الصفحة
     * @param int|null $offset     الموقع البدائي (للـ pagination)
     * @param array    $filters    فلاتر (مثل: ['request_id' => 123])
     * @param string   $orderBy    الحقل المراد الترتيب عليه
     * @param string   $orderDir   اتجاه الترتيب (ASC | DESC)
     * @return array   ['items' => array, 'total' => int]
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return [
            'items' => $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir),
            'total' => $this->repo->count($tenantId, $filters),
        ];
    }

    /**
     * جلب نسخة واحدة حسب المعرف
     *
     * @param int $tenantId
     * @param int $id
     * @return array|null
     */
    public function get(int $tenantId, int $id): ?array
    {
        return $this->repo->find($tenantId, $id);
    }

    /**
     * إنشاء نسخة جديدة للشهادة
     *
     * @param int   $tenantId
     * @param int   $userId     معرف المستخدم الذي يقوم بالعملية
     * @param array $data       بيانات النسخة (request_id, version_number, ...)
     * @return int  معرف النسخة الجديدة (ID)
     * @throws InvalidArgumentException إذا كانت البيانات غير صالحة
     * @throws RuntimeException إذا فشل الحفظ
     */
    public function create(int $tenantId, int $userId, array $data): int
    {
        // يمكن إضافة validator هنا إذا أردت فصل التحقق
        // مثال: (new CertificatesVersionsValidator())->validate($data);

        $id = $this->repo->save($tenantId, $data);

        $this->logs->insert(
            requestId: (int)($data['request_id'] ?? 0),
            userId: $userId,
            actionType: 'create',
            notes: "تم إنشاء نسخة جديدة برقم: $id"
        );

        return $id;
    }

    /**
     * تحديث نسخة موجودة
     *
     * @param int   $tenantId
     * @param int   $userId
     * @param array $data       يجب أن يحتوي على 'id'
     * @return int  معرف النسخة المحدثة
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $tenantId, int $userId, array $data): int
    {
        if (empty($data['id']) || !is_numeric($data['id'])) {
            throw new InvalidArgumentException("معرف النسخة (id) مطلوب للتحديث");
        }

        $id = (int)$data['id'];

        $updated = $this->repo->save($tenantId, $data);

        $existing = $this->repo->find($tenantId, $id);
        $requestId = $existing['request_id'] ?? 0;

        $this->logs->insert(
            requestId: (int)$requestId,
            userId: $userId,
            actionType: 'update',
            notes: "تم تحديث النسخة رقم: $id"
        );

        return $updated;
    }

    /**
     * حذف نسخة (مع تسجيل العملية)
     *
     * @param int $tenantId
     * @param int $userId
     * @param int $id
     * @return bool نجاح الحذف
     * @throws RuntimeException إذا فشل الحذف
     */
    public function delete(int $tenantId, int $userId, int $id): bool
    {
        $existing = $this->repo->find($tenantId, $id);

        if ($existing === null) {
            throw new RuntimeException("النسخة غير موجودة (ID: $id)");
        }

        $success = $this->repo->delete($tenantId, $id);

        if (!$success) {
            throw new RuntimeException("فشل حذف النسخة (ID: $id)");
        }

        $this->logs->insert(
            requestId: (int)($existing['request_id'] ?? 0),
            userId: $userId,
            actionType: 'delete',
            notes: "تم حذف النسخة رقم: $id"
        );

        return true;
    }
}