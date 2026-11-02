<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use App\Support\ValueObjects\LocalizedText;

final class CommitteeEntityPresenter
{
    private const string CODE_PREFIX = 'C-';

    /**
     * @return array<string, mixed>
     */
    public static function present(Assessment $assessment, CommitteeEntityVisibility $visibility): array
    {
        $entity = $assessment->entity;
        $region = $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null;

        return match ($visibility) {
            CommitteeEntityVisibility::CodeOnly => ['visibility' => $visibility->value, 'code' => self::codeOf($assessment), 'region' => $region],
            CommitteeEntityVisibility::NameOnly => ['visibility' => $visibility->value, 'name' => $entity->name->toArray(), 'region' => $region],
            CommitteeEntityVisibility::Full => [
                'visibility' => $visibility->value,
                ...EntityPresenter::summary($entity),
                ...EntityPresenter::profile($entity),
            ],
        };
    }

    public static function label(Assessment $assessment, CommitteeEntityVisibility $visibility): LocalizedText
    {
        if (! $visibility->showsName()) {
            $code = self::codeOf($assessment);

            return new LocalizedText($code, $code);
        }

        return $assessment->entity->name->toLocalizedText();
    }

    public static function codeOf(Assessment $assessment): string
    {
        return self::CODE_PREFIX.strtoupper(substr(hash_hmac('sha256', 'committee:'.$assessment->public_id, config()->string('app.key')), 0, 8));
    }
}
