<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Files\SignedDownload;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Identity\FileAccessIdentityGate;
use App\Services\Watermark\ViewerStampRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\UrlGenerator;

final readonly class SignedDownloadIssuer
{
    public const string ROUTE = 'files.download';

    public function __construct(
        private UrlGenerator $urls,
        private Repository $config,
        private FileAccessIdentityGate $identityGate,
        private ViewerStampRule $stampRule,
    ) {}

    public function issue(StoredFile $file, User $user): SignedDownload
    {
        if (! $file->isScannedClean()) {
            throw FileRejected::notClean();
        }

        if ($this->stampRule->issuesStampedLink($user, $file->purpose)) {
            $this->identityGate->assertMayOpen($user, $file->purpose);
        }

        $expiresAt = CarbonImmutable::now()->addMinutes($this->config->integer('moashir.finance.download_ttl_minutes'));
        $url = $this->urls->temporarySignedRoute(self::ROUTE, $expiresAt, ['file' => $file->public_id, 'user' => $user->id]);

        return new SignedDownload($url, $expiresAt, $file->original_name);
    }
}
