<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use Illuminate\Support\Facades\DB;

final readonly class DiscardDraftVersion
{
    public function __construct(private DraftCriteriaTransaction $transaction, private CriteriaAuditTrail $audit) {}

    public function handle(User $actor, CriteriaVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $draft = $this->transaction->lockDraft($version->id);
            $draft->delete();

            $this->audit->record(AuditEvent::CriteriaVersionDiscarded, $draft, ['status' => $draft->status->value], ['discarded' => true]);
        });
    }
}
