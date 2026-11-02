<?php

declare(strict_types=1);

namespace App\Data\Audit;

use App\Enums\Audit\AuditEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class AuditEntry
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  list<string>  $tags
     */
    public function __construct(
        public AuditEvent $event,
        public Model $subject,
        public array $oldValues = [],
        public array $newValues = [],
        public array $tags = [],
    ) {}
}
