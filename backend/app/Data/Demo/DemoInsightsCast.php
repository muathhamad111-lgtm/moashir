<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Exceptions\Console\DemoDataRefused;
use App\Models\Identity\User;

final readonly class DemoInsightsCast
{
    private const int ENTITY_OWNERS = 3;

    public User $goldOwner;

    public User $selfOnlyOwner;

    public User $mixedOwner;

    /**
     * @param  list<User>  $entityOwnersInInstallOrder
     */
    public function __construct(array $entityOwnersInInstallOrder, public User $assessor, public User $systemAdmin, public User $contentOfficer)
    {
        if (count($entityOwnersInInstallOrder) !== self::ENTITY_OWNERS) {
            throw DemoDataRefused::missingPrerequisite('the three demo entities and their owner accounts are not all present');
        }

        [$this->goldOwner, $this->selfOnlyOwner, $this->mixedOwner] = $entityOwnersInInstallOrder;
    }
}
