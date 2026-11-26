<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;

final class ProfileSectionsPresenter
{
    /**
     * @return list<array{key: string, revision: string, fields: list<array{key: string, requirement: string}>}>
     */
    public static function present(ProfileFieldValues $values): array
    {
        return array_map(static fn (ProfileSection $section): array => [
            'key' => $section->value,
            'revision' => ProfileSectionRevision::of($values, $section),
            'fields' => array_map(static fn (ProfileSectionField $field): array => [
                'key' => $field->value,
                'requirement' => $field->requirement()->value,
            ], $section->fields()),
        ], ProfileSection::cases());
    }
}
