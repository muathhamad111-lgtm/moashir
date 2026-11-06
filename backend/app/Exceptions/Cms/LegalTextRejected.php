<?php

declare(strict_types=1);

namespace App\Exceptions\Cms;

use App\Enums\Identity\TermsVersionStatus;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class LegalTextRejected extends ApiException
{
    public static function notPublished(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'legal.not_published'));
    }

    public static function draftExists(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'legal.draft_exists'));
    }

    public static function notDraft(TermsVersionStatus $status): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'legal.version_not_draft', ['version_status' => [$status->value]]));
    }

    public static function versionTaken(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'legal.version_taken', [
            'version' => [__('errors.legal.version_taken')],
        ]));
    }
}
