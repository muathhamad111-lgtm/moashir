<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\AiAnswerFinish;

final readonly class AiAnswer
{
    public function __construct(
        public string $text,
        public AiAnswerFinish $finish,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $providerRequestId,
        public int $latencyMs,
    ) {}
}
