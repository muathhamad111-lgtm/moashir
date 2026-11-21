<?php

declare(strict_types=1);

namespace App\Exceptions\Cms;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class CmsRejected extends ApiException
{
    public static function sectionKeyTaken(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'cms.section_key_taken', [
            'key' => [__('errors.cms.section_key_taken')],
        ]));
    }

    public static function limitReached(int $limit): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'cms.limit_reached', [], [], ['limit' => $limit]));
    }

    public static function pageEmpty(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'cms.page_empty'));
    }

    public static function pageNotPublished(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'cms.page_not_published'));
    }
}
