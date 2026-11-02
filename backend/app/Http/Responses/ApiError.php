<?php

declare(strict_types=1);

namespace App\Http\Responses;

final readonly class ApiError
{
    /**
     * @param  array<string, list<string>>  $fields
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $status,
        public string $code,
        public array $fields = [],
        public array $headers = [],
        public array $meta = [],
    ) {}

    public function message(): string
    {
        return __('errors.'.$this->code);
    }
}
