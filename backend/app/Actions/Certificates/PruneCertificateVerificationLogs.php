<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificates\CertificateVerificationLog;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class PruneCertificateVerificationLogs
{
    public function __construct(private Repository $config) {}

    public function handle(): int
    {
        $cutoff = Date::now()->subDays($this->config->integer('moashir.certificates.verification_log_retention_days'));

        return CertificateVerificationLog::query()->where('created_at', '<', $cutoff)->toBase()->delete();
    }
}
