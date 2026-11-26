<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\Settings\LogoVariant;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Cms\PublicContentCache;
use App\Services\Files\StoredFileRemover;
use App\Services\Platform\TransactionLock;
use App\Services\Settings\BrandingAuditTrail;
use App\Services\Settings\DefaultBrandColors;
use App\Services\Settings\SettingsChangeReason;
use Illuminate\Support\Facades\DB;

final readonly class ResetBranding
{
    public function __construct(
        private SettingsChangeReason $reason,
        private TransactionLock $lock,
        private StoredFileRemover $remover,
        private BrandingAuditTrail $audit,
        private PublicContentCache $publicContent,
        private DefaultBrandColors $defaults,
    ) {}

    public function handle(User $actor, ?string $reason): void
    {
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor): void {
            $this->lock->lock(BrandingTheme::LOCK);
            $theme = BrandingTheme::query()->lockForUpdate()->findOrFail(BrandingTheme::SINGLETON_ID);
            $before = $this->audit->snapshotOf($theme);
            $columns = LogoVariant::columns();
            $logoIds = array_values(array_filter(array_map(static fn (string $column): mixed => $theme->getAttribute($column), $columns)));

            $defaults = $this->defaults->get();

            $theme->forceFill([
                'primary_color' => $defaults->primary,
                'secondary_color' => $defaults->secondary,
                'color_overrides' => $defaults->overrides->toArray(),
                'sidebar_top_color' => $defaults->rail->top,
                'sidebar_bottom_color' => $defaults->rail->bottom,
                ...array_fill_keys($columns, null),
                'updated_by_user_id' => $actor->id,
            ])->save();

            foreach (StoredFile::query()->whereKey($logoIds)->get() as $logo) {
                $this->remover->remove($logo);
            }

            $this->audit->record($theme, $before, 'reset');
            $this->reason->alertIfSuperAdmin($actor);
            DB::afterCommit(fn () => $this->publicContent->forgetSiteSettings());
        });
    }
}
