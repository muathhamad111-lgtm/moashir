<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoInsightsCast;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Identity\User;

final readonly class DemoInsightsCastResolver
{
    public function __construct(private DemoRecords $records) {}

    public function resolve(): DemoInsightsCast
    {
        return new DemoInsightsCast(
            $this->records->entityOwners(),
            $this->staffMember(RoleKey::Assessor),
            $this->staffMember(RoleKey::SystemAdmin),
            $this->staffMember(RoleKey::ContentOfficer),
        );
    }

    private function staffMember(RoleKey $role): User
    {
        return $this->records->staffWithRole($role)
            ?? throw DemoDataRefused::missingPrerequisite(sprintf('no demo %s account exists', $role->value));
    }
}
