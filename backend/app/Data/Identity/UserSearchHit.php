<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Contracts\Search\SearchHit;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;

final readonly class UserSearchHit implements SearchHit
{
    /**
     * @param  list<RoleKey>  $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public UserStatus $status,
        public array $roles,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self($user->id, $user->name, $user->email, $user->effectiveStatus(), $user->roleKeys());
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'roles' => array_map(static fn (RoleKey $role): string => $role->value, $this->roles),
        ];
    }
}
