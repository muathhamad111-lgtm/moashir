<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AssignmentReasonRules;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;

final readonly class AssignmentReasonPolicy
{
    public function __construct(private SettingsStore $settings) {}

    public function rules(): AssignmentReasonRules
    {
        return new AssignmentReasonRules($this->flag(SettingKey::AssignReasonRequired), $this->flag(SettingKey::ReassignReasonRequired));
    }

    private function flag(SettingKey $key): bool
    {
        $value = $this->settings->get($key);

        return is_bool($value) ? $value : (bool) $key->defaultValue();
    }
}
