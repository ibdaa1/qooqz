<?php
namespace V1\modules\Theme\Services;

use V1\modules\Theme\Repositories\PdoThemeRepository;

class ThemeService
{
    private PdoThemeRepository $repository;

    public function __construct(PdoThemeRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * إرجاع الألوان كـ CSS Variables String
     * مثال: ":root { --primary: #fff; }"
     */
    public function getActiveCssVariables(): string
    {
        $rows = $this->repository->getActiveColors();
        $vars = array_map(function($row) {
            return "{$row['variable']}: {$row['value']};";
        }, $rows);

        return ":root { " . implode(" ", $vars) . " }";
    }

    /**
     * إرجاع البيانات كـ JSON للوحة التحكم
     */
    public function getAllForAdmin(): array
    {
        return $this->repository->getAllColors();
    }

    public function updateColor(int $id, string $hexValue): bool
    {
        return $this->repository->updateColor($id, $hexValue);
    }
}