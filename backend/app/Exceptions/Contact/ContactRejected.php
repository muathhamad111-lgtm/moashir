<?php

declare(strict_types=1);

namespace App\Exceptions\Contact;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class ContactRejected extends ApiException
{
    public static function closed(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'contact.message_closed'));
    }

    public static function assigneeNotEligible(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'contact.assignee_not_eligible', [
            'assignee_id' => [__('errors.contact.assignee_not_eligible')],
        ]));
    }
}
