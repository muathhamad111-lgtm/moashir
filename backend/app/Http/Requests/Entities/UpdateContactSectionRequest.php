<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityProfileWrite;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;
use App\Support\Entities\EntityProfileInput;

final class UpdateContactSectionRequest extends ProfileSectionRequest
{
    public function section(): ProfileSection
    {
        return ProfileSection::Contact;
    }

    protected function prohibitedFields(): array
    {
        return [ProfileSectionField::OfficialEmail->value];
    }

    protected function sectionRules(): array
    {
        return [
            ...EntityProfileInput::contactRules(),
            'website_url' => EntityProfileInput::websiteUrlRules(),
            'marketing_consent' => ['required', 'boolean'],
        ];
    }

    protected function write(array $validated): EntityProfileWrite
    {
        return new EntityProfileWrite(
            [EntityProfileField::WebsiteUrl->value => EntityProfileInput::websiteUrl($validated)?->value],
            EntityProfileInput::contact($validated),
            filter_var($validated['marketing_consent'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }
}
