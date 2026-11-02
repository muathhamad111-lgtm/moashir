<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Data\Integrations\CredentialChanges;
use App\Support\ValueObjects\SecretValue;
use Illuminate\Support\Arr;

final class CredentialInput
{
    /**
     * @param  array<array-key, mixed>  $validated
     * @param  list<string>  $publicFields
     * @param  list<string>  $secretFields
     */
    public static function changes(array $validated, string $prefix, array $publicFields, array $secretFields): CredentialChanges
    {
        $public = [];
        $secrets = [];

        foreach ($publicFields as $field) {
            if (Arr::has($validated, $prefix.$field)) {
                $public[$field] = Arr::get($validated, $prefix.$field);
            }
        }

        foreach ($secretFields as $field) {
            $value = Arr::get($validated, $prefix.$field);

            if (is_array($value)) {
                $secrets[$field] = null;
            } elseif (is_string($value) && $value !== '') {
                $secrets[$field] = new SecretValue($value);
            }
        }

        return new CredentialChanges($public, $secrets);
    }
}
