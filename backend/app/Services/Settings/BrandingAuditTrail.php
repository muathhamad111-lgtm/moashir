<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\LogoVariant;
use App\Models\Settings\BrandingTheme;
use App\Services\Audit\AuditRecorder;

final readonly class BrandingAuditTrail
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshotOf(BrandingTheme $theme): array
    {
        $snapshot = ['accent_token' => $theme->accent_token->value];

        foreach (LogoVariant::columns() as $column) {
            $snapshot[$column] = $theme->getAttribute($column);
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function record(BrandingTheme $theme, array $before, string $change): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::BrandingUpdated, $theme, $before, $this->snapshotOf($theme), [$change]));
    }
}
