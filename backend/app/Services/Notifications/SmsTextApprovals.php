<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Notifications\SmsTextApproval;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Integrations\MsegatTemplateGuard;
use App\Support\Integrations\SmsTemplateMatcher;

/**
 * Whether a notification's SMS text may be sent: an active Msegat account restricted to approved templates
 * only sends a text that matches one of them (its `:variables` read as template placeholders).
 */
final class SmsTextApprovals
{
    private const string VARIABLE = '/:([a-z][a-z0-9_]*)/i';

    private ?IntegrationCredential $msegat = null;

    private bool $looked = false;

    public function of(string $text): SmsTextApproval
    {
        $msegat = $this->activeMsegat();

        if (! $msegat instanceof IntegrationCredential || $msegat->publicValue('template_restricted') !== true) {
            return SmsTextApproval::NotRequired;
        }

        if ($msegat->providerRef(MsegatTemplateGuard::TEMPLATES_LOADED_AT) === null) {
            return SmsTextApproval::TemplatesNotLoaded;
        }

        $asTemplate = (string) preg_replace(self::VARIABLE, '{$1}', $text);

        return SmsTemplateMatcher::matchesAny($asTemplate, MsegatTemplateGuard::approvedBodies($msegat))
            ? SmsTextApproval::Approved
            : SmsTextApproval::Pending;
    }

    private function activeMsegat(): ?IntegrationCredential
    {
        if (! $this->looked) {
            $this->msegat = IntegrationCredential::query()
                ->ofKind(IntegrationKind::Sms)
                ->where('provider', IntegrationProvider::Msegat)
                ->where('is_active', true)
                ->first();
            $this->looked = true;
        }

        return $this->msegat;
    }
}
