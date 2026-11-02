<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\User;
use Illuminate\Http\Request;

final readonly class TermsAcceptanceRecorder
{
    public function __construct(private Request $request) {}

    /**
     * @param  list<int>  $termsVersionIds
     */
    public function accept(User $user, array $termsVersionIds): void
    {
        foreach (array_unique($termsVersionIds) as $termsVersionId) {
            $user->termsAcceptances()->create([
                'terms_version_id' => $termsVersionId,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]);
        }
    }
}
