<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Support\ValueObjects\LocalizedText;

final class RequestNotices
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    public const string FOR_ASSESSOR = 'assessor';

    private const string ROOT = 'notifications.assessments.';

    /**
     * @param  array<string, LocalizedText|string>  $extra
     */
    public static function for(NotificationCode $code, string $audience, Assessment $assessment, string $link, array $extra = []): EntityNotice
    {
        return self::variant($code, $code->value.'.'.$audience, $assessment, $link, $extra);
    }

    /**
     * @param  array<string, LocalizedText|string>  $extra
     */
    public static function variant(NotificationCode $code, string $key, Assessment $assessment, string $link, array $extra = []): EntityNotice
    {
        $replacements = ['number' => $assessment->request_number, 'entity' => $assessment->entity->name->toLocalizedText(), ...$extra];

        $root = self::ROOT.$assessment->path->value.'.'.$key;

        return new EntityNotice(
            $code,
            LocalizedText::fromTranslationWith($root.'.title', $replacements),
            LocalizedText::fromTranslationWith($root.'.body', $replacements),
            $link,
            true,
            true,
        );
    }
}
