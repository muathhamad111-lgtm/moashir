<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Entities\EntityNotice;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Files\StoredFile;
use App\Services\Entities\EntityNotifier;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class FileAlerts
{
    private const string TRANSLATIONS = 'notifications.files.';

    public function __construct(private EntityNotifier $notifier, private Cache $cache, private Config $config) {}

    public function quarantined(StoredFile $file): void
    {
        $this->toSystemAdministrators(new EntityNotice(
            NotificationCode::RequestProcessingFailed,
            LocalizedText::fromTranslation(self::TRANSLATIONS.'quarantined.title'),
            LocalizedText::fromTranslationWith(self::TRANSLATIONS.'quarantined.body', [
                'purpose' => LocalizedText::fromTranslation(self::TRANSLATIONS.'purposes.'.$file->purpose->value),
                'file' => $file->public_id,
            ]),
            null,
            false,
            true,
        ));
    }

    public function infectedUploadRefused(StoredFilePurpose $purpose): void
    {
        if (! $this->claimCooldown('files:alert:infected-upload:'.$purpose->value)) {
            return;
        }

        $this->toSystemAdministrators(new EntityNotice(
            NotificationCode::RequestProcessingFailed,
            LocalizedText::fromTranslation(self::TRANSLATIONS.'infected_upload.title'),
            LocalizedText::fromTranslationWith(self::TRANSLATIONS.'infected_upload.body', [
                'purpose' => LocalizedText::fromTranslation(self::TRANSLATIONS.'purposes.'.$purpose->value),
            ]),
            null,
            false,
            true,
        ));
    }

    public function scanAbandoned(): void
    {
        if (! $this->claimCooldown('files:alert:scan-abandoned')) {
            return;
        }

        $this->toSystemAdministrators(new EntityNotice(
            NotificationCode::ExternalServiceDown,
            LocalizedText::fromTranslation(self::TRANSLATIONS.'scan_abandoned.title'),
            LocalizedText::fromTranslation(self::TRANSLATIONS.'scan_abandoned.body'),
            null,
            true,
            true,
        ));
    }

    private function claimCooldown(string $key): bool
    {
        $minutes = max(1, $this->config->integer('moashir.integrations.alert_cooldown_minutes'));

        return $this->cache->add($key, true, $minutes * 60);
    }

    private function toSystemAdministrators(EntityNotice $notice): void
    {
        $this->notifier->toRole(RoleKey::SystemAdmin, $notice, null);
    }
}
