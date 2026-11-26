<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RemoveOwnAvatar;
use App\Actions\Identity\ShowOwnAvatar;
use App\Actions\Identity\UploadOwnAvatar;
use App\Http\Requests\Identity\UploadAvatarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Support\Identity\AccountAvatarLink;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MyAvatarController
{
    public function show(#[CurrentUser] User $user, ShowOwnAvatar $showAvatar): StreamedResponse
    {
        $image = $showAvatar->handle($user);

        return new StreamedResponse(static function () use ($image): void {
            fpassthru($image->stream);
            fclose($image->stream);
        }, StreamedResponse::HTTP_OK, [
            'Content-Type' => $image->mimeType,
            'Content-Length' => (string) $image->sizeBytes,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $image->fileName),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(UploadAvatarRequest $request, #[CurrentUser] User $user, UploadOwnAvatar $uploadAvatar): JsonResponse
    {
        $avatar = $uploadAvatar->handle($user, $request->upload());

        return ApiResponse::data(['avatar' => AccountAvatarLink::of($avatar)], Response::HTTP_CREATED);
    }

    public function destroy(#[CurrentUser] User $user, RemoveOwnAvatar $removeAvatar): JsonResponse
    {
        $removeAvatar->handle($user);

        return ApiResponse::data(['avatar' => null]);
    }
}
