<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentStatus;
use App\Events\Assessments\PaidDraftReminded;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Periods\PeriodicSettingsReader;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class RemindInactivePaidDrafts
{
    private const int CHUNK = 200;

    public function __construct(private PeriodicSettingsReader $settings, private EntityFreezeGuard $freezeGuard) {}

    public function handle(): int
    {
        $cutoff = Date::now()->subDays($this->settings->read()->paidDraftReminderDays);
        $reminded = 0;

        $this->freezeGuard->excludeFrozen(Assessment::query()->ofPath(AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Draft))
            ->whereHas('payment', static fn (Builder $payment): Builder => $payment->where('status', PaymentStatus::Paid))
            ->where(static fn (Builder $quiet): Builder => $quiet->whereNull('paid_draft_reminded_at')->orWhere('paid_draft_reminded_at', '<=', $cutoff))
            ->withEntityActivity()
            ->with('entity')
            ->chunkById(self::CHUNK, function (Collection $drafts) use ($cutoff, &$reminded): void {
                foreach ($drafts as $draft) {
                    $reminded += $this->remindIfInactive($draft, $cutoff) ? 1 : 0;
                }
            }, 'assessments.id', 'id');

        return $reminded;
    }

    private function remindIfInactive(Assessment $draft, CarbonInterface $cutoff): bool
    {
        $lastActivity = $draft->entity_activity_at ?? $draft->created_at;

        if ($lastActivity->greaterThan($cutoff) || ! $this->claim($draft, $cutoff)) {
            return false;
        }

        PaidDraftReminded::dispatch($draft);

        return true;
    }

    private function claim(Assessment $draft, CarbonInterface $cutoff): bool
    {
        return Assessment::query()
            ->whereKey($draft->id)
            ->where(static fn (Builder $quiet): Builder => $quiet->whereNull('paid_draft_reminded_at')->orWhere('paid_draft_reminded_at', '<=', $cutoff))
            ->update(['paid_draft_reminded_at' => Date::now()]) === 1;
    }
}
