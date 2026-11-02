<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\Integrations\SmsTemplateKey;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Settings\IntegrationSettings;
use App\Support\Integrations\SmsTemplateMatcher;

final readonly class MsegatTemplateGuard
{
    public const string APPROVED_TEMPLATES = 'approved_templates';

    public const string TEMPLATES_LOADED_AT = 'templates_loaded_at';

    public function __construct(private IntegrationSettings $settings) {}

    public function assertApproved(?IntegrationCredential $msegat): void
    {
        if (! $msegat instanceof IntegrationCredential || $msegat->publicValue('template_restricted') !== true) {
            return;
        }

        if ($msegat->providerRef(self::TEMPLATES_LOADED_AT) === null) {
            throw IntegrationRejected::templatesNotLoaded();
        }

        $approved = self::approvedBodies($msegat);

        foreach (SmsTemplateKey::cases() as $key) {
            foreach ($this->settings->smsTemplate($key)->toArray() as $locale => $body) {
                if (! SmsTemplateMatcher::matchesAny($body, $approved)) {
                    throw IntegrationRejected::templateNotApproved('templates.'.$key->value.'.body.'.$locale);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function approvedBodies(IntegrationCredential $msegat): array
    {
        $templates = $msegat->providerRef(self::APPROVED_TEMPLATES);
        $bodies = [];

        foreach (is_array($templates) ? $templates : [] as $template) {
            if (is_array($template) && is_string($template['body'] ?? null)) {
                $bodies[] = $template['body'];
            }
        }

        return $bodies;
    }
}
