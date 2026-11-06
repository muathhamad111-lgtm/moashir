<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\Settings\AccentToken;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Files\StoredFileRemover;
use App\Services\Platform\TransactionLock;
use App\Services\Settings\BrandingAuditTrail;
use App\Services\Settings\SettingsChangeReason;
use Illuminate\Support\Facades\DB;

final readonly class ResetBranding
{
    public function __construct(
        private SettingsChangeReason $reason,
        private TransactionLock $lock,
        private StoredFileRemover $remover,
        private BrandingAuditTrail $audit,
    ) {}

    public function handle(User $actor, ?string $reason): void
    {
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor): void {
            $this->lock->lock(BrandingTheme::LOCK);
            $theme = BrandingTheme::query()->lockForUpdate()->findOrFail(BrandingTheme::SINGLETON_ID);
            $before = $this->audit->snapshotOf($theme);
            $logoIds = array_values(array_filter([$theme->logo_light_file_id, $theme->logo_dark_file_id]));

            $theme->forceFill([
                'accent_token' => AccentToken::BrandDefault,
                'logo_light_file_id' => null,
                'logo_dark_file_id' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            foreach (StoredFile::query()->whereKey($logoIds)->get() as $logo) {
                $this->remover->remove($logo);
            }

            $this->audit->record($theme, $before, 'reset');
            $this->reason->alertIfSuperAdmin($actor);
        });
    }
}
