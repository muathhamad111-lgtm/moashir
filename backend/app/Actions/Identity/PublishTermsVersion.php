<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\TermsPublication;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Identity\TermsVersionPublisher;

final readonly class PublishTermsVersion
{
    public function __construct(private TermsVersionPublisher $publisher) {}

    public function handle(User $publisher, TermsPublication $publication): TermsVersion
    {
        return $this->publisher->publishNew($publisher, $publication);
    }
}
