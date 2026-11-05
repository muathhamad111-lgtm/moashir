<?php

declare(strict_types=1);

namespace App\Jobs\Certificates;

use App\Exceptions\Documents\DocumentRenderingUnavailable;
use App\Models\Certificates\Certificate;
use App\Services\Certificates\CertificatePdfPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

final class RenderCertificatePdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 85;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $certificateId) {}

    public function uniqueId(): string
    {
        return 'render-certificate-pdf:'.$this->certificateId;
    }

    public function handle(CertificatePdfPublisher $publisher): void
    {
        $certificate = Certificate::query()->find($this->certificateId);

        if (! $certificate instanceof Certificate) {
            return;
        }

        try {
            $publisher->ensure($certificate);
        } catch (DocumentRenderingUnavailable) {
            Log::info('certificates.pdf_deferred', ['certificate_id' => $this->certificateId]);
        }
    }
}
