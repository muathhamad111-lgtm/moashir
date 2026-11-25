<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the staff users list: the user record without the personal fields (phone, gender, national ID),
 * which only the single-user record carries (data minimization).
 *
 * @property-read User $resource
 */
final class UserListResource extends JsonResource
{
    private const array RECORD_ONLY_FIELDS = ['phone', 'gender', 'national_id_masked'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_diff_key(new UserResource($this->resource)->toArray($request), array_flip(self::RECORD_ONLY_FIELDS));
    }
}
