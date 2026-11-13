<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Actions\Contact\AddContactMessageNote;
use App\Actions\Contact\AssignContactMessage;
use App\Actions\Contact\CloseContactMessage;
use App\Actions\Contact\ReopenContactMessage;
use App\Actions\Contact\ReplyToContactMessage;
use App\Http\Requests\Contact\AssignContactMessageRequest;
use App\Http\Requests\Contact\ContactEntryRequest;
use App\Http\Requests\Identity\OptionalReasonRequest;
use App\Http\Resources\Contact\ContactMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ContactMessageWorkflowController
{
    public function assign(AssignContactMessageRequest $request, ContactMessage $contactMessage, #[CurrentUser] User $actor, AssignContactMessage $assign): JsonResponse
    {
        return $this->detail($assign->handle($actor, $contactMessage, $request->assigneeId(), $request->reason()));
    }

    public function note(ContactEntryRequest $request, ContactMessage $contactMessage, #[CurrentUser] User $actor, AddContactMessageNote $note): JsonResponse
    {
        $note->handle($actor, $contactMessage, $request->action());

        return $this->detail($contactMessage, Response::HTTP_CREATED);
    }

    public function reply(ContactEntryRequest $request, ContactMessage $contactMessage, #[CurrentUser] User $actor, ReplyToContactMessage $reply): JsonResponse
    {
        $reply->handle($actor, $contactMessage, $request->action());

        return $this->detail($contactMessage, Response::HTTP_CREATED);
    }

    public function close(OptionalReasonRequest $request, ContactMessage $contactMessage, #[CurrentUser] User $actor, CloseContactMessage $close): JsonResponse
    {
        return $this->detail($close->handle($actor, $contactMessage, $request->reason()));
    }

    public function reopen(OptionalReasonRequest $request, ContactMessage $contactMessage, #[CurrentUser] User $actor, ReopenContactMessage $reopen): JsonResponse
    {
        return $this->detail($reopen->handle($actor, $contactMessage, $request->reason()));
    }

    private function detail(ContactMessage $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::resource(new ContactMessageResource($message->fresh(ContactInboxController::DETAIL_RELATIONS) ?? $message), $status);
    }
}
