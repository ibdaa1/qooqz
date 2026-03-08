<?php
namespace V1\modules\Settings\Contracts;

interface SettingsRepositoryInterface
{
    public function getPublicSettings(): array;
    public function getAllSettings(): array;
    public function findById(int $id): ?array;
    public function update(int $id, string $value): bool;
}