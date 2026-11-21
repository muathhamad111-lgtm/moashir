<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Actions\Contact\ListContactHandlers;
use App\Actions\Contact\ListContactMessages;
use App\Actions\Contact\SummarizeContactMessages;
use App\Http\Requests\Contact\IndexContactMessagesRequest;
use App\Http\Resources\Contact\ContactHandlerResource;
use App\Http\Resources\Contact\ContactMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Contact\ContactMessage;
use Illuminate\Http\JsonResponse;

final class ContactInboxController
{
    public const array DETAIL_RELATIONS = ['sender', 'entity', 'assignee', 'closedBy', 'entries.author'];

    public function index(IndexContactMessagesRequest $request, ListContactMessages $list, SummarizeContactMessages $summary): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->filters()), ContactMessageResource::class, ['stats' => $summary->handle()]);
    }

    public function show(ContactMessage $contactMessage): JsonResponse
    {
        return ApiResponse::resource(new ContactMessageResource($contactMessage->load(self::DETAIL_RELATIONS)));
    }

    public function assignees(ListContactHandlers $handlers): JsonResponse
    {
        return ApiResponse::data(ContactHandlerResource::collection($handlers->handle())->resolve(request()));
    }
}
