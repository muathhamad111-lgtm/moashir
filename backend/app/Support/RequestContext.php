<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class RequestContext
{
    private string $requestId;

    private ?string $reason = null;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function useRequestId(string $requestId): void
    {
        if (Str::isUuid($requestId)) {
            $this->requestId = $requestId;
        }
    }

    public function recordReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
