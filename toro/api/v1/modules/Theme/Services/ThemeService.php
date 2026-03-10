<?php
/**
 * TORO — v1/modules/Theme/Services/ThemeService.php
 * كل منطق الثيم — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\NotFoundException;
use Shared\Domain\Exceptions\ValidationException;
use Shared\Helpers\AuditLogger;

class ThemeService
{
    private PdoThemeRepository $repository;

    public function __construct(PdoThemeRepository $repository)
    {
        $this->repository = $repository;
    }

    // ── CSS Variables (public endpoint) ────────────────────────
    public function getActiveCssVariables(): string
    {
        $rows = $this->repository->getActiveColors();
        if (empty($rows)) return ':root {}';

        $vars = array_map(fn($row) => "  {$row['variable']}: {$row['value']};", $rows);
        return ":root {\n" . implode("\n", $vars) . "\n}";
    }

    // ── List with filters ──────────────────────────────────────
    public function list(array $filters = []): array
    {
        $items = $this->repository->findAll($filters);
        $total = $this->repository->countAll($filters);

        return [
            'items'  => $items,
            'total'  => $total,
            'limit'  => max(1, min((int)($filters['limit']  ?? 100), 500)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ── Get by ID ──────────────────────────────────────────────
    public function getById(int $id): array
    {
        $color = $this->repository->findById($id);
        if (!$color) throw new NotFoundException("لون الثيم #{$id} غير موجود");
        return $color;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(CreateThemeDTO $dto, int $actorId): array
    {
        // تحقق من عدم تكرار المتغير
        if ($this->repository->findByVariable($dto->variable)) {
            throw new ValidationException(
                'هذا المتغير موجود مسبقاً',
                ['variable' => 'يجب أن يكون فريداً']
            );
        }

        $id = $this->repository->create([
            'variable'  => $dto->variable,
            'value'     => $dto->value,
            'is_active' => $dto->isActive,
        ]);

        AuditLogger::log('theme_color_created', $actorId, 'theme_colors', $id);
        return $this->repository->findById($id) ?? [];
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, UpdateThemeDTO $dto, int $actorId): array
    {
        $existing = $this->repository->findById($id);
        if (!$existing) throw new NotFoundException("لون الثيم #{$id} غير موجود");

        // تحقق من uniqueness إذا تغيّر المتغير
        if ($dto->variable !== null && $dto->variable !== $existing['variable']) {
            $conflict = $this->repository->findByVariable($dto->variable);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException(
                    'هذا المتغير مستخدم مسبقاً',
                    ['variable' => 'يجب أن يكون فريداً']
                );
            }
        }

        $updateData = array_filter([
            'variable'  => $dto->variable,
            'value'     => $dto->value,
            'is_active' => $dto->isActive,
        ], fn($v) => $v !== null);

        if ($updateData) $this->repository->update($id, $updateData);

        AuditLogger::log('theme_color_updated', $actorId, 'theme_colors', $id);
        return $this->repository->findById($id) ?? [];
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repository->findById($id);
        if (!$existing) throw new NotFoundException("لون الثيم #{$id} غير موجود");

        $this->repository->delete($id);
        AuditLogger::log('theme_color_deleted', $actorId, 'theme_colors', $id);
    }
}
