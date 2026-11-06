<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Audit\AuditEntry;
use App\Data\Insights\MaturityTextEdit;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\User;
use App\Models\Insights\MaturityInterpretation;
use App\Services\Audit\AuditRecorder;
use App\Services\Settings\SettingsLock;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\DB;

final readonly class SaveMaturityText
{
    public const string LOCK = 'maturity_texts';

    public function __construct(private SettingsLock $lock, private AuditRecorder $audit) {}

    public function handle(User $actor, MaturityTextEdit $edit): MaturityInterpretation
    {
        return DB::transaction(function () use ($actor, $edit): MaturityInterpretation {
            $this->lock->lock(self::LOCK);
            $row = MaturityInterpretation::query()
                ->where('band', $edit->band)
                ->where('main_criterion_code', $edit->mainCriterionCode)
                ->first() ?? new MaturityInterpretation(['band' => $edit->band, 'main_criterion_code' => $edit->mainCriterionCode]);
            $before = $row->text;

            if ($before instanceof LocalizedText && $before->toArray() === $edit->text->toArray()) {
                return $row;
            }

            $row->forceFill(['text' => $edit->text, 'updated_by_user_id' => $actor->id])->save();
            $this->audit->record(new AuditEntry(AuditEvent::MaturityTextsUpdated, $row, [
                'text' => $before?->toArray(),
            ], [
                'band' => $edit->band->value,
                'main_criterion_code' => $edit->mainCriterionCode,
                'text' => $edit->text->toArray(),
            ]));

            return $row;
        });
    }
}
