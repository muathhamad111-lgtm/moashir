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
        $snapshot = [
            'primary_color' => $theme->primary_color,
            'secondary_color' => $theme->secondary_color,
            'color_overrides' => $theme->colors()->overrides->toArray(),
            'sidebar_top_color' => $theme->sidebar_top_color,
            'sidebar_bottom_color' => $theme->sidebar_bottom_color,
        ];

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
