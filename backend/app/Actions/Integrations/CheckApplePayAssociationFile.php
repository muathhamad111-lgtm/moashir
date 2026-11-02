<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Exceptions\Integrations\IntegrationRejected;
use App\Services\Integrations\ApplePayAssociationFile;
use App\Support\Integrations\IntegrationUrls;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final readonly class CheckApplePayAssociationFile
{
    public function __construct(
        private ApplePayAssociationFile $file,
        private IntegrationUrls $urls,
        private Factory $http,
        private Repository $config,
    ) {}

    /**
     * @return array{url: string, reachable: bool, status: int|null, matches: bool}
     */
    public function handle(): array
    {
        $current = $this->file->current() ?? throw IntegrationRejected::associationFileMissing();
        $url = $this->urls->applePayAssociation();

        try {
            $response = $this->http
                ->timeout($this->config->integer('moashir.integrations.timeout_seconds.reachability'))
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (ConnectionException) {
            return ['url' => $url, 'reachable' => false, 'status' => null, 'matches' => false];
        }

        return [
            'url' => $url,
            'reachable' => $response->successful(),
            'status' => $response->status(),
            'matches' => $response->successful() && hash_equals($current->sha256, hash('sha256', $response->body())),
        ];
    }
}
