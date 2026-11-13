<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Settings\BrandingTheme;
use App\Services\Audit\AuditRecorder;

final readonly class BrandingAuditTrail
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{accent_token: string, logo_light_file_id: int|null, logo_dark_file_id: int|null}
     */
    public function snapshotOf(BrandingTheme $theme): array
    {
        return [
            'accent_token' => $theme->accent_token->value,
            'logo_light_file_id' => $theme->logo_light_file_id,
            'logo_dark_file_id' => $theme->logo_dark_file_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function record(BrandingTheme $theme, array $before, string $change): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::BrandingUpdated, $theme, $before, $this->snapshotOf($theme), [$change]));
    }
}
