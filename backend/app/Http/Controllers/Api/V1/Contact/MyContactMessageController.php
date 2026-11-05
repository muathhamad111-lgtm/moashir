<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Actions\Contact\AddSenderFollowUp;
use App\Actions\Contact\FindOwnContactMessage;
use App\Actions\Contact\ListOwnContactMessages;
use App\Actions\Contact\MarkContactRepliesSeen;
use App\Actions\Contact\SummarizeOwnContactMessages;
use App\Http\Requests\Contact\IndexOwnContactMessagesRequest;
use App\Http\Requests\Contact\SenderFollowUpRequest;
use App\Http\Resources\Contact\OwnContactMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyContactMessageController
{
    public function index(IndexOwnContactMessagesRequest $request, #[CurrentUser] User $sender, ListOwnContactMessages $list, SummarizeOwnContactMessages $summary): JsonResponse
    {
        return ApiResponse::paginated($list->handle($sender, $request->statuses(), $request->perPage()), OwnContactMessageResource::class, $summary->handle($sender)->toMeta());
    }

    public function show(string $message, #[CurrentUser] User $sender, FindOwnContactMessage $find): JsonResponse
    {
        return ApiResponse::resource(new OwnContactMessageResource($find->handle($sender, $message)));
    }

    public function followUp(SenderFollowUpRequest $request, string $message, #[CurrentUser] User $sender, FindOwnContactMessage $find, AddSenderFollowUp $followUp): JsonResponse
    {
        $own = $find->handle($sender, $message);
        $followUp->handle($sender, $own, $request->body());

        return ApiResponse::resource(new OwnContactMessageResource($find->handle($sender, $message)), Response::HTTP_CREATED);
    }

    public function markSeen(string $message, #[CurrentUser] User $sender, FindOwnContactMessage $find, MarkContactRepliesSeen $markSeen): JsonResponse
    {
        $markSeen->handle($find->handle($sender, $message));

        return ApiResponse::resource(new OwnContactMessageResource($find->handle($sender, $message)));
    }
}
