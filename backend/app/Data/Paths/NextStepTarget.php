<?php

declare(strict_types=1);

namespace App\Data\Paths;

final readonly class NextStepTarget
{
    public const string SELF = 'paths.self';

    public const string ACCREDITED = 'paths.accredited';

    public const string ACCREDITED_REQUEST = 'paths.accredited.request';

    public const string AWARD = 'paths.award';

    private const array HREFS = [
        self::SELF => '/paths/self',
        self::ACCREDITED => '/paths/accredited',
        self::AWARD => '/paths/award',
    ];

    /**
     * @param  array<string, int>  $params
     * @param  array<string, string>  $query
     */
    public function __construct(public string $route, public array $params = [], public array $query = []) {}

    public static function accreditedRequest(int $assessmentId): self
    {
        return new self(self::ACCREDITED_REQUEST, ['id' => $assessmentId], ['tab' => 'request']);
    }

    public function href(): string
    {
        $path = self::HREFS[$this->route] ?? '/paths/accredited/'.($this->params['id'] ?? '');

        return $this->query === [] ? $path : $path.'?'.http_build_query($this->query);
    }

    /**
     * @return array{route: string, params: object, query: object, href: string}
     */
    public function toArray(): array
    {
        return ['route' => $this->route, 'params' => (object) $this->params, 'query' => (object) $this->query, 'href' => $this->href()];
    }
}
