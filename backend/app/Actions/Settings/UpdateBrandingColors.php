<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\BrandColors;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Platform\TransactionLock;
use App\Services\Settings\BrandingAuditTrail;
use App\Services\Settings\SettingsChangeReason;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBrandingColors
{
    public function __construct(
        private SettingsChangeReason $reason,
        private TransactionLock $lock,
        private BrandingAuditTrail $audit,
    ) {}

    public function handle(User $actor, BrandColors $colors, ?string $reason): void
    {
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor, $colors): void {
            $this->lock->lock(BrandingTheme::LOCK);
            $theme = BrandingTheme::query()->lockForUpdate()->findOrFail(BrandingTheme::SINGLETON_ID);

            if ($theme->colors()->equals($colors)) {
                return;
            }

            $before = $this->audit->snapshotOf($theme);
            $theme->forceFill([
                'primary_color' => $colors->primary,
                'secondary_color' => $colors->secondary,
                'color_overrides' => $colors->overrides->toArray(),
                'updated_by_user_id' => $actor->id,
            ])->save();
            $this->audit->record($theme, $before, 'colors');
            $this->reason->alertIfSuperAdmin($actor);
        });
    }
}
