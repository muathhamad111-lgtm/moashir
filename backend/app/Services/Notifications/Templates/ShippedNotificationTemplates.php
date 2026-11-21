<?php

declare(strict_types=1);

namespace App\Services\Notifications\Templates;

use App\Support\Notifications\Templates\NotificationTemplateCatalog;

/**
 * The catalog of editable notification texts, read from the shipped language files (never from the overrides).
 */
final class ShippedNotificationTemplates
{
    private ?NotificationTemplateCatalog $catalog = null;

    public function catalog(): NotificationTemplateCatalog
    {
        return $this->catalog ??= NotificationTemplateCatalog::fromLines($this->lines('ar'), $this->lines('en'));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function lines(string $locale): array
    {
        $lines = require lang_path($locale.'/notifications.php');

        return is_array($lines) ? $lines : [];
    }
}
