<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Data\Audit\AuditEntry;
use App\Data\Nda\NdaRegisterExport;
use App\Enums\Audit\AuditEvent;
use App\Enums\Nda\NdaAcceptanceFilter;
use App\Exceptions\Nda\NdaRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Nda\NdaAcceptanceCheck;
use App\Services\Nda\NdaRegisterQuery;
use Illuminate\Support\Facades\Date;

/**
 * Prepares the audited CSV export of the acceptance register for the current version. The file name carries the
 * date only, never a name or address.
 */
final readonly class ExportNdaAcceptances
{
    public function __construct(
        private NdaAcceptanceCheck $check,
        private NdaRegisterQuery $register,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, NdaAcceptanceFilter $filter, string $locale): NdaRegisterExport
    {
        $version = $this->check->current() ?? throw NdaRejected::noCurrentVersion();
        $users = $this->register->users($version, $filter);
        $rowCount = (clone $users)->count();

        $this->audit->record(new AuditEntry(AuditEvent::NdaAcceptancesExported, $actor, [], [
            'terms_version_id' => $version->id,
            'filter' => $filter->value,
            'row_count' => $rowCount,
        ], ['export']));

        return new NdaRegisterExport('nda-acceptances-'.Date::now()->format('Y-m-d').'.csv', $rowCount, $version, $users, $locale);
    }
}
