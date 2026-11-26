<?php

declare(strict_types=1);

namespace App\Data\Nda;

use App\Enums\Identity\RoleKey;

final readonly class NdaSettingsUpdate
{
    /**
     * @param  list<RoleKey>|null  $roles
     */
    public function __construct(
        public ?array $roles,
        public ?int $reminderDays,
        public string $reason,
    ) {}

    /**
     * @return list<string>|null
     */
    public function roleValues(): ?array
    {
        return $this->roles === null ? null : array_map(static fn (RoleKey $role): string => $role->value, $this->roles);
    }
}
