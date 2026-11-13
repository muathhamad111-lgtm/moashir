<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\EntityAccounts;
use App\Enums\Identity\BlindIndexPurpose;
use App\Enums\Identity\SignInIdentifierType;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\SignInIdentifier;
use App\Support\ValueObjects\UnifiedNumber;

final readonly class SignInAccountResolver
{
    public function __construct(private EntityAccounts $entityAccounts, private BlindIndex $blindIndex) {}

    public function resolve(SignInIdentifier $identifier): ?User
    {
        return match ($identifier->type) {
            SignInIdentifierType::Email => User::query()->withEmail(new EmailAddress($identifier->value))->first(),
            SignInIdentifierType::Mobile => User::query()
                ->where('phone_index', $this->blindIndex->of(BlindIndexPurpose::Phone, $identifier->value))
                ->whereNotNull('phone_verified_at')
                ->first(),
            SignInIdentifierType::UnifiedNumber => $this->entityAccounts->ownerWithUnifiedNumber(new UnifiedNumber($identifier->value)),
        };
    }
}
