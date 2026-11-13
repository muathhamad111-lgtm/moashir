<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Identity\SignInIdentifierType;

final readonly class SignInIdentifier
{
    private const array SEPARATORS = [' ', '-'];

    private function __construct(public SignInIdentifierType $type, public string $value) {}

    public static function tryParse(string $input): ?self
    {
        $trimmed = trim($input);

        if (str_contains($trimmed, '@')) {
            return self::email($trimmed);
        }

        $compact = str_replace(self::SEPARATORS, '', $trimmed);
        $unifiedNumber = UnifiedNumber::tryFrom($compact);

        if ($unifiedNumber instanceof UnifiedNumber) {
            return new self(SignInIdentifierType::UnifiedNumber, $unifiedNumber->value);
        }

        $phone = PhoneNumber::tryFromSaudiInput($compact);

        return $phone instanceof PhoneNumber ? new self(SignInIdentifierType::Mobile, $phone->value) : null;
    }

    public static function ofEmail(EmailAddress $email): self
    {
        return new self(SignInIdentifierType::Email, $email->value);
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->type->value.'|'.$this->value);
    }

    private static function email(string $input): ?self
    {
        $normalized = mb_strtolower($input);

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) === false
            ? null
            : new self(SignInIdentifierType::Email, $normalized);
    }
}
