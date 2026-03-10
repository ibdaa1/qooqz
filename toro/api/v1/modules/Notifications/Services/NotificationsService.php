<?php
/**
 * TORO — v1/modules/Notifications/Services/NotificationsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class NotificationsService
{
    public function __construct(private readonly NotificationsRepositoryInterface $repo) {}

    // ── Templates ──────────────────────────────────────────────
    public function listTemplates(array $filters = []): array
    {
        $items = $this->repo->findAllTemplates($filters);
        foreach ($items as &$tmpl) {
            $tmpl['translations'] = $this->repo->getTemplateTranslations((int)$tmpl['id']);
        }
        return [
            'items'  => $items,
            'total'  => $this->repo->countTemplates($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getTemplateById(int $id): array
    {
        $tmpl = $this->repo->findTemplateById($id);
        if (!$tmpl) throw new NotFoundException("القالب #{$id} غير موجود");
        $tmpl['translations'] = $this->repo->getTemplateTranslations($id);
        return $tmpl;
    }

    public function createTemplate(array $raw): array
    {
        NotificationsValidator::template($raw);

        $existing = $this->repo->findTemplateBySlugChannel(
            trim($raw['slug']),
            $raw['channel']
        );
        if ($existing) {
            throw new ValidationException(['slug' => 'القالب موجود بالفعل لهذه القناة']);
        }

        $id = $this->repo->createTemplate([
            'slug'      => trim($raw['slug']),
            'channel'   => $raw['channel'],
            'is_active' => isset($raw['is_active']) ? (int)$raw['is_active'] : 1,
        ]);

        return $this->getTemplateById($id);
    }

    public function updateTemplate(int $id, array $raw): array
    {
        $tmpl = $this->repo->findTemplateById($id);
        if (!$tmpl) throw new NotFoundException("القالب #{$id} غير موجود");

        $update = array_filter([
            'slug'      => isset($raw['slug'])      ? trim($raw['slug'])      : null,
            'channel'   => $raw['channel']           ?? null,
            'is_active' => isset($raw['is_active'])  ? (int)$raw['is_active'] : null,
        ], fn($v) => $v !== null);

        if (!empty($update)) {
            $this->repo->updateTemplate($id, $update);
        }

        return $this->getTemplateById($id);
    }

    public function deleteTemplate(int $id): void
    {
        $tmpl = $this->repo->findTemplateById($id);
        if (!$tmpl) throw new NotFoundException("القالب #{$id} غير موجود");
        $this->repo->deleteTemplate($id);
    }

    public function upsertTranslation(int $templateId, array $raw): array
    {
        NotificationsValidator::templateTranslation($raw);

        $tmpl = $this->repo->findTemplateById($templateId);
        if (!$tmpl) throw new NotFoundException("القالب #{$templateId} غير موجود");

        $this->repo->upsertTemplateTranslation(
            $templateId,
            (int)$raw['language_id'],
            [
                'subject' => $raw['subject'] ?? null,
                'body'    => $raw['body'],
            ]
        );

        return $this->getTemplateById($templateId);
    }

    // ── Notifications Log ──────────────────────────────────────
    public function listLogs(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAllLogs($filters),
            'total'  => $this->repo->countLogs($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getLogById(int $id): array
    {
        $log = $this->repo->findLogById($id);
        if (!$log) throw new NotFoundException("سجل الإشعار #{$id} غير موجود");
        return $log;
    }

    public function createLog(array $raw): array
    {
        NotificationsValidator::log($raw);

        $id = $this->repo->createLog([
            'user_id'     => isset($raw['user_id'])     ? (int)$raw['user_id']     : null,
            'template_id' => isset($raw['template_id']) ? (int)$raw['template_id'] : null,
            'channel'     => $raw['channel'],
            'recipient'   => trim($raw['recipient']),
            'status'      => $raw['status'] ?? 'pending',
        ]);

        return $this->getLogById($id);
    }

    public function updateLogStatus(int $id, string $status): array
    {
        $allowed = ['sent', 'failed', 'pending'];
        if (!in_array($status, $allowed)) {
            throw new ValidationException(['status' => 'الحالة غير صالحة']);
        }

        $log = $this->repo->findLogById($id);
        if (!$log) throw new NotFoundException("سجل الإشعار #{$id} غير موجود");

        $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
        $this->repo->updateLogStatus($id, $status, $sentAt);

        return $this->getLogById($id);
    }
}
