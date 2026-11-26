<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Enums\Entities\EntityProfileField;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class EntityUniqueViolation
{
    private const array INDEXES = [
        'entities_unified_number_unique' => EntityProfileField::UnifiedNumber,
        'entities_license_number_unique' => EntityProfileField::LicenseNumber,
    ];

    public static function toValidation(UniqueConstraintViolationException $violation): ?ValidationException
    {
        foreach (self::INDEXES as $index => $field) {
            if (str_contains($violation->getMessage(), $index)) {
                return ValidationException::withMessages([$field->value => [__('validation.custom.'.$field->value.'.unique')]]);
            }
        }

        return null;
    }
}
