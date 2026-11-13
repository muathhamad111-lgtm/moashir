<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Enums\Platform\DemoRecordKind;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Finance\Payment;
use App\Models\Platform\DemoRecord;
use App\Services\Demo\DemoPurgeGuard;
use Illuminate\Database\Eloquent\Builder;

final readonly class PreviewDemoPurge
{
    public function __construct(private DemoPurgeGuard $guard) {}

    /**
     * @return array{register_records: int, entities: int, users: int, assessments: int, certificates: int, payments: int}
     */
    public function handle(): array
    {
        $this->guard->assertMayPreview();
        $entityIds = $this->recordIdsOf(DemoRecordKind::Entity);
        $assessments = Assessment::query()->where(static fn (Builder $query): Builder => $query
            ->whereIn('entity_id', $entityIds)
            ->orWhereIn('id', DemoRecord::query()->where('kind', DemoRecordKind::InsightsAssessment)->select('record_id')));

        return [
            'register_records' => DemoRecord::query()->count(),
            'entities' => DemoRecord::query()->where('kind', DemoRecordKind::Entity)->count(),
            'users' => DemoRecord::query()->where('kind', DemoRecordKind::User)->count(),
            'assessments' => (clone $assessments)->count(),
            'certificates' => Certificate::query()->whereIn('assessment_id', (clone $assessments)->select('id'))->count(),
            'payments' => Payment::query()->whereIn('entity_id', $entityIds)->count(),
        ];
    }

    /**
     * @return Builder<DemoRecord>
     */
    private function recordIdsOf(DemoRecordKind $kind): Builder
    {
        return DemoRecord::query()->where('kind', $kind)->select('record_id');
    }
}
