<?php

declare(strict_types=1);

namespace App\Exceptions\Notifications;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class NotificationPreferenceRejected extends ApiException
{
    public static function notApplicable(int $index): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'notification_preference.not_applicable', [
            "preferences.{$index}.code" => [__('errors.notification_preference.not_applicable')],
        ]));
    }

    public static function locked(int $index): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'notification_preference.locked', [
            "preferences.{$index}.channel" => [__('errors.notification_preference.locked')],
        ]));
    }
}
