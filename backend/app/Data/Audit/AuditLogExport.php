<?php

declare(strict_types=1);

namespace App\Data\Audit;

use App\Models\Audit\Audit;
use App\Models\Identity\User;
use Illuminate\Support\LazyCollection;

final readonly class AuditLogExport
{
    /**
     * @param  LazyCollection<int, Audit>  $rows
     */
    public function __construct(
        public string $fileName,
        public int $rowCount,
        public LazyCollection $rows,
        public User $viewer,
    ) {}
}
