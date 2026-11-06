<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\TermsVersion;

final class TermsVersionSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function of(TermsVersion $version): array
    {
        return [
            'kind' => $version->kind,
            'version' => $version->version,
            'title' => $version->title?->toArray(),
            'body_sha256' => [
                'ar' => hash('sha256', $version->body->ar),
                'en' => hash('sha256', $version->body->en),
            ],
            'body_characters' => [
                'ar' => mb_strlen($version->body->ar),
                'en' => mb_strlen($version->body->en),
            ],
        ];
    }
}
