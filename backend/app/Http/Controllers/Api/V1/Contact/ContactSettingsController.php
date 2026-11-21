<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contact;

use App\Actions\Contact\UpdateContactSettings;
use App\Http\Requests\Contact\UpdateContactSettingsRequest;
use App\Http\Resources\Contact\ContactSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Contact\ContactRetention;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ContactSettingsController
{
    public function show(ContactRetention $retention): JsonResponse
    {
        return ApiResponse::resource(new ContactSettingsResource($retention->months()));
    }

    public function update(UpdateContactSettingsRequest $request, #[CurrentUser] User $actor, UpdateContactSettings $update, ContactRetention $retention): JsonResponse
    {
        $update->handle($actor, $request->settingsUpdate());

        return ApiResponse::resource(new ContactSettingsResource($retention->months()));
    }
}
