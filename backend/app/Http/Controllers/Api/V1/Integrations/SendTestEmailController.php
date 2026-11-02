<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\SendTestEmail;
use App\Http\Requests\Integrations\SendTestEmailRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SendTestEmailController
{
    public function __invoke(SendTestEmailRequest $request, #[CurrentUser] User $actor, SendTestEmail $send): JsonResponse
    {
        return ApiResponse::data(['sent' => true, 'redirected_to_test_mailbox' => $send->handle($actor, $request->recipient())]);
    }
}
