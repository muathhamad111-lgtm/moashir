<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Support\ValueObjects\LocalizedText;

final readonly class StaffActor
{
    /**
     * @param  list<array{key: string, label: LocalizedText}>  $roles
     */
    public function __construct(public ?string $name, public array $roles) {}

    public static function unknown(): self
    {
        return new self(null, []);
    }

    /**
     * @return array{name: string|null, roles: list<array{key: string, label: array{ar: string, en: string}}>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'roles' => array_map(static fn (array $role): array => ['key' => $role['key'], 'label' => $role['label']->toArray()], $this->roles),
        ];
    }
}
