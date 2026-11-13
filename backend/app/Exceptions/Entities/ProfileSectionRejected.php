<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Enums\Entities\ProfileSection;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class ProfileSectionRejected extends ApiException
{
    public static function readOnly(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'forbidden'));
    }

    public static function stale(ProfileSection $section, string $currentRevision): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'profile.section_stale', meta: [
            'section' => $section->value,
            'revision' => $currentRevision,
        ]));
    }
}
