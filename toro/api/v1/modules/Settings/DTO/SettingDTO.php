<?php
namespace App\Modules\Settings\DTO;

class SettingDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $group,
        public readonly string $key,
        public readonly ?string $value,
        public readonly string $type,
        public readonly bool $isPublic
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            group: $data['group'],
            key: $data['key'],
            value: $data['value'] ?? null,
            type: $data['type'],
            isPublic: (bool)$data['is_public']
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'group'     => $this->group,
            'key'       => $this->key,
            'value'     => $this->value,
            'type'      => $this->type,
            'is_public' => $this->isPublic,
        ];
    }
}