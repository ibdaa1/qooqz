<?php
/**
 * TORO — v1/modules/Notifications/Contracts/NotificationsRepositoryInterface.php
 */
declare(strict_types=1);

interface NotificationsRepositoryInterface
{
    // Templates
    public function findAllTemplates(array $filters = []): array;
    public function countTemplates(array $filters = []): int;
    public function findTemplateById(int $id): ?array;
    public function findTemplateBySlugChannel(string $slug, string $channel): ?array;
    public function createTemplate(array $data): int;
    public function updateTemplate(int $id, array $data): bool;
    public function deleteTemplate(int $id): bool;

    // Template Translations
    public function getTemplateTranslations(int $templateId): array;
    public function upsertTemplateTranslation(int $templateId, int $languageId, array $data): int;

    // Notifications Log
    public function findAllLogs(array $filters = []): array;
    public function countLogs(array $filters = []): int;
    public function createLog(array $data): int;
    public function findLogById(int $id): ?array;
    public function updateLogStatus(int $id, string $status, ?string $sentAt): bool;
}
