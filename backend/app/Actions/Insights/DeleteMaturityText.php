<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Insights\InsightsRejected;
use App\Models\Insights\MaturityInterpretation;
use App\Services\Audit\AuditRecorder;
use App\Services\Settings\SettingsLock;
use Illuminate\Support\Facades\DB;

final readonly class DeleteMaturityText
{
    public function __construct(private SettingsLock $lock, private AuditRecorder $audit) {}

    public function handle(MaturityInterpretation $text): void
    {
        if ($text->isGeneric()) {
            throw InsightsRejected::genericTextNotDeletable();
        }

        DB::transaction(function () use ($text): void {
            $this->lock->lock(SaveMaturityText::LOCK);
            $locked = MaturityInterpretation::query()->whereKey($text->id)->lockForUpdate()->firstOrFail();
            $this->audit->record(new AuditEntry(AuditEvent::MaturityTextsUpdated, $locked, [
                'text' => $locked->text?->toArray(),
            ], [
                'band' => $locked->band->value,
                'main_criterion_code' => $locked->main_criterion_code,
                'text' => null,
                'deleted' => true,
            ]));
            $locked->delete();
        });
    }
}
