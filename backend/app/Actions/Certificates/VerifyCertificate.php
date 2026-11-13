<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Certificates\CertificateVerification;
use App\Enums\Certificates\VerificationOutcome;
use App\Models\Certificates\Certificate;
use App\Models\Certificates\CertificateVerificationLog;
use App\Services\Certificates\VerificationCode;
use App\Support\Periods\LocalDay;
use Illuminate\Contracts\Config\Repository;

final readonly class VerifyCertificate
{
    private const string IP_HASH_ALGORITHM = 'sha256';

    public function __construct(private VerificationCode $codes, private Repository $config) {}

    public function handle(string $code, ?string $ipAddress): CertificateVerification
    {
        $normalized = $this->codes->normalize($code);
        $certificate = $normalized === null ? null : Certificate::query()->where('verification_code', $normalized)->first();
        $verification = $certificate instanceof Certificate
            ? new CertificateVerification(VerificationOutcome::ofCertificate($certificate, LocalDay::today()), $certificate)
            : new CertificateVerification(VerificationOutcome::NotFound);

        CertificateVerificationLog::query()->create([
            'certificate_id' => $certificate?->id,
            'outcome' => $verification->outcome,
            'ip_hash' => hash_hmac(self::IP_HASH_ALGORITHM, (string) $ipAddress, $this->config->string('app.key')),
        ]);

        return $verification;
    }
}
