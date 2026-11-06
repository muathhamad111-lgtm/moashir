<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Actions\Contact\EraseContactMessagesOfAddress;
use App\Actions\Contact\ExportContactMessagesOfAddress;
use App\Http\Requests\Contact\ContactAddressPrivacyRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ContactPersonalDataController
{
    public function export(ContactAddressPrivacyRequest $request, #[CurrentUser] User $actor, ExportContactMessagesOfAddress $export): JsonResponse
    {
        return ApiResponse::data($export->handle($actor, $request->addressRequest()));
    }

    public function erase(ContactAddressPrivacyRequest $request, EraseContactMessagesOfAddress $erase): JsonResponse
    {
        return ApiResponse::data($erase->handle($request->addressRequest()));
    }
}
