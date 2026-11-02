<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Models\Entities\Entity;
use Illuminate\Support\Facades\Date;

final class MarketingConsentRecorder
{
    public function record(Entity $entity, bool $consents): void
    {
        $hasConsent = $entity->marketing_consent_at !== null;

        if ($consents === $hasConsent) {
            return;
        }

        $entity->forceFill($consents
            ? ['marketing_consent_at' => Date::now(), 'marketing_unsubscribed_at' => null]
            : ['marketing_consent_at' => null, 'marketing_unsubscribed_at' => Date::now()])->save();
    }
}
