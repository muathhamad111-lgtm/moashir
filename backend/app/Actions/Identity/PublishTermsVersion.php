<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\TermsPublication;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class PublishTermsVersion
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $publisher, TermsPublication $publication): TermsVersion
    {
        return DB::transaction(function () use ($publisher, $publication): TermsVersion {
            $previous = TermsVersion::query()->current()->where('kind', $publication->kind->value)->lockForUpdate()->first();
            $previous?->forceFill(['is_current' => false])->save();

            $version = TermsVersion::query()->create([
                'kind' => $publication->kind->value,
                'version' => $publication->version,
                'body' => $publication->body,
                'effective_from' => $publication->effectiveFrom->toDateString(),
                'is_current' => true,
                'published_by_user_id' => $publisher->id,
            ]);

            $this->audit->record(new AuditEntry(
                AuditEvent::TermsPublished,
                $version,
                ['current_version' => $previous?->version],
                ['kind' => $publication->kind->value, 'current_version' => $publication->version],
            ));

            return $version;
        });
    }
}
