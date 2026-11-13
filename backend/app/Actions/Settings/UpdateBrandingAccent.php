<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\PaletteColor;
use App\Enums\Settings\AccentToken;
use App\Exceptions\Settings\BrandingRejected;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Platform\TransactionLock;
use App\Services\Settings\BrandingAuditTrail;
use App\Services\Settings\BrandPalette;
use App\Services\Settings\ContrastValidator;
use App\Services\Settings\SettingsChangeReason;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBrandingAccent
{
    public function __construct(
        private BrandPalette $palette,
        private ContrastValidator $contrast,
        private SettingsChangeReason $reason,
        private TransactionLock $lock,
        private BrandingAuditTrail $audit,
    ) {}

    public function handle(User $actor, string $token, ?string $reason): void
    {
        $color = $this->colorFor($token);
        $failures = $this->contrast->failures($color);

        if ($failures !== []) {
            throw BrandingRejected::contrastFail($failures);
        }

        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor, $color): void {
            $this->lock->lock(BrandingTheme::LOCK);
            $theme = BrandingTheme::query()->lockForUpdate()->findOrFail(BrandingTheme::SINGLETON_ID);

            if ($theme->accent_token === $color->token) {
                return;
            }

            $before = $this->audit->snapshotOf($theme);
            $theme->forceFill(['accent_token' => $color->token, 'updated_by_user_id' => $actor->id])->save();
            $this->audit->record($theme, $before, 'accent');
            $this->reason->alertIfSuperAdmin($actor);
        });
    }

    private function colorFor(string $token): PaletteColor
    {
        $accent = AccentToken::tryFrom($token);
        $color = $accent instanceof AccentToken ? $this->palette->colorOf($accent) : null;

        return $color ?? throw BrandingRejected::tokenUnknown();
    }
}
