<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Actions\Contact\SubmitContactMessage;
use App\Http\Requests\Contact\SubmitContactMessageRequest;
use App\Http\Resources\Contact\ContactReceiptResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SubmitContactMessageController
{
    public function __invoke(SubmitContactMessageRequest $request, SubmitContactMessage $submit): JsonResponse
    {
        return ApiResponse::resource(new ContactReceiptResource($submit->handle($request->submission(), $request->sender())), Response::HTTP_CREATED);
    }
}
