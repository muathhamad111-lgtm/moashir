<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\BlindIndexPurpose;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;

final readonly class BlindIndex
{
    private const string KEY_CONTEXT = 'moashir.blind-index.v1';

    private const string BASE64_PREFIX = 'base64:';

    public function __construct(private Repository $config) {}

    public function of(BlindIndexPurpose $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose->value.'|'.$value, $this->key());
    }

    private function key(): string
    {
        $appKey = $this->config->string('app.key');
        $material = str_starts_with($appKey, self::BASE64_PREFIX)
            ? base64_decode(substr($appKey, strlen(self::BASE64_PREFIX)), true)
            : $appKey;

        if ($material === false || $material === '') {
            throw new RuntimeException('The application key is required to compute blind indexes.');
        }

        return hash_hmac('sha256', self::KEY_CONTEXT, $material, true);
    }
}
