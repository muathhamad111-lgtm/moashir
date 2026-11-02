<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\AcceptInvitation;
use App\Http\Requests\Identity\AcceptInvitationRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class AcceptInvitationController
{
    public function __invoke(string $token, AcceptInvitationRequest $request, AcceptInvitation $acceptInvitation, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond($acceptInvitation->handle($token, $request->password(), $request->acceptedTerms()));
    }
}
