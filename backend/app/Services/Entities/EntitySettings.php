<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Enums\Identity\EmailChangePolicy;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;

final readonly class EntitySettings
{
    public function __construct(private Repository $config) {}

    public function emailChangePolicy(): EmailChangePolicy
    {
        return EmailChangePolicy::tryFrom(ScalarValue::string($this->config->get('moashir.entities.email_change_policy')))
            ?? EmailChangePolicy::RequiresApproval;
    }

    public function verificationReminderCooldownMinutes(): int
    {
        return max(1, ScalarValue::int($this->config->get('moashir.entities.verification_reminder_cooldown_minutes'), 15));
    }

    public function exportMaxRows(): int
    {
        return max(1, ScalarValue::int($this->config->get('moashir.entities.export_max_rows'), 10000));
    }
}
