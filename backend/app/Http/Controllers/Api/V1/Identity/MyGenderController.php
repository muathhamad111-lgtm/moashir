<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RecordOwnGender;
use App\Http\Requests\Identity\RecordOwnGenderRequest;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class MyGenderController
{
    public function __invoke(RecordOwnGenderRequest $request, #[CurrentUser] User $user, RecordOwnGender $record): Response
    {
        $record->handle($user, $request->gender());

        return response()->noContent();
    }
}
