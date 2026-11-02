<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Models\Integrations\IntegrationCredential;

final class CredentialCompleteness
{
    public static function of(IntegrationCredential $row): bool
    {
        foreach ($row->provider->requiredPublicFields() as $field) {
            $value = $row->publicValue($field);

            if (in_array($value, [null, '', []], true)) {
                return false;
            }
        }

        $secrets = $row->secrets ?? [];

        foreach ($row->provider->requiredSecretFields() as $field) {
            if (($secrets[$field] ?? '') === '') {
                return false;
            }
        }

        return true;
    }
}
