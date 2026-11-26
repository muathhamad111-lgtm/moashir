<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use App\Support\Security\SensitiveUrl;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class SensitiveLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: SensitiveUrl::redact($record->message), context: $this->redactAll($record->context));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redactAll(array $values): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            is_string($value) => SensitiveUrl::redact($value),
            is_array($value) => $this->redactAll($value),
            default => $value,
        }, $values);
    }
}
