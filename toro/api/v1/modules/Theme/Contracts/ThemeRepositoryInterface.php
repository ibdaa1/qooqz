<?php
declare(strict_types=1);

interface ThemeRepositoryInterface
{
    public function getActiveColors(): array;
    public function getAllColors(): array;
    public function updateColor(int $id, string $value): bool;
}