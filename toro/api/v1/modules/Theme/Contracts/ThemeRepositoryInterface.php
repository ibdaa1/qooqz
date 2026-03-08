<?php
namespace V1\modules\Theme\Contracts;

interface ThemeRepositoryInterface
{
    public function getActiveColors(): array;
    public function getAllColors(): array;
    public function updateColor(int $id, string $value): bool;
}