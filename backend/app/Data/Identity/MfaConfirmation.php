<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Models\Identity\User;

final readonly class MfaConfirmation
{
    /**
     * @param  list<string>  $recoveryCodes
     */
    public function __construct(public array $recoveryCodes, public ?User $signedInUser) {}
}
