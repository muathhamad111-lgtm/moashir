<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;

final readonly class DemoPeople
{
    /**
     * @param  array<string, DemoUserAccount>  $staff
     * @param  list<DemoUserAccount>  $entityOwners
     */
    public function __construct(public array $staff, public array $entityOwners) {}

    public function staffMember(RoleKey $role): User
    {
        return $this->staff[$role->value]->user;
    }

    public function insightsCast(): DemoInsightsCast
    {
        return new DemoInsightsCast(
            array_map(static fn (DemoUserAccount $owner): User => $owner->user, $this->entityOwners),
            $this->staffMember(RoleKey::Assessor),
            $this->staffMember(RoleKey::SystemAdmin),
            $this->staffMember(RoleKey::ContentOfficer),
        );
    }

    /**
     * @return list<DemoAccount>
     */
    public function accounts(): array
    {
        return array_map(
            static fn (DemoUserAccount $installed): DemoAccount => $installed->account,
            [...array_values($this->staff), ...$this->entityOwners],
        );
    }
}
