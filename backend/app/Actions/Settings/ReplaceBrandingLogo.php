<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\BrandingLogoUpload;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Files\StoredFileRemover;
use App\Services\Platform\TransactionLock;
use App\Services\Settings\BrandingAuditTrail;
use App\Services\Settings\BrandingLogoIntake;
use App\Services\Settings\SettingsChangeReason;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

final readonly class ReplaceBrandingLogo
{
    public function __construct(
        private SettingsChangeReason $reason,
        private BrandingLogoIntake $intake,
        private StoredFileRemover $remover,
        private TransactionLock $lock,
        private BrandingAuditTrail $audit,
    ) {}

    public function handle(User $actor, BrandingLogoUpload $upload): StoredFile
    {
        $this->reason->record($actor, $upload->reason);
        $logo = $this->intake->accept($upload->file, BrandingTheme::query()->findOrFail(BrandingTheme::SINGLETON_ID), $actor);

        return DB::transaction(function () use ($actor, $upload, $logo): StoredFile {
            $this->lock->lock(BrandingTheme::LOCK);
            $theme = BrandingTheme::query()->lockForUpdate()->findOrFail(BrandingTheme::SINGLETON_ID);
            $before = $this->audit->snapshotOf($theme);
            $column = $upload->variant->column();
            $previousId = ScalarValue::int($theme->getAttribute($column));

            $theme->forceFill([$column => $logo->id, 'updated_by_user_id' => $actor->id])->save();
            $this->removePrevious($previousId);

            $this->audit->record($theme, $before, 'logo_'.$upload->variant->value);
            $this->reason->alertIfSuperAdmin($actor);

            return $logo;
        });
    }

    private function removePrevious(int $previousId): void
    {
        $previous = $previousId > 0 ? StoredFile::query()->find($previousId) : null;

        if ($previous instanceof StoredFile) {
            $this->remover->remove($previous);
        }
    }
}
