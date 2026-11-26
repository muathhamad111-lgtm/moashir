<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Data\Audit\AuditEntry;
use App\Data\Audit\AuditLogExport;
use App\Data\Audit\AuditLogFilters;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Audit\AuditLogRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditLogQuery;
use App\Services\Audit\AuditRecorder;
use App\Support\RequestContext;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class ExportAuditLogs
{
    private const int CHUNK = 500;

    public function __construct(
        private AuditLogQuery $query,
        private AuditRecorder $audit,
        private RequestContext $context,
        private Repository $config,
    ) {}

    public function handle(User $viewer, AuditLogFilters $filters, ?string $reason): AuditLogExport
    {
        if ($viewer->isSuperAdmin() && ($reason === null || $reason === '')) {
            throw AuditLogRejected::reasonRequired();
        }

        $query = $this->query->for($viewer, $filters);
        $rowCount = (clone $query)->count();
        $limit = $this->config->integer('moashir.audit.export_max_rows');

        if ($rowCount > $limit) {
            throw AuditLogRejected::exportTooLarge($limit);
        }

        $this->context->recordReason($reason);
        $this->audit->record(new AuditEntry(AuditEvent::AuditLogExported, $viewer, [], [
            'row_count' => $rowCount,
            'filters' => $filters->describe(),
        ], ['export']));

        return new AuditLogExport('audit-log-'.Date::now()->format('Ymd-His').'.csv', $rowCount, $query->with('user')->lazyByIdDesc(self::CHUNK), $viewer);
    }
}
