<?php

declare(strict_types=1);

it('reports a healthy service inside the standard envelope', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson([
            'data' => ['status' => 'ok', 'message' => 'الخدمة تعمل.'],
            'meta' => [],
            'errors' => null,
        ]);
});

it('answers the framework liveness probe', function (): void {
    $this->get('/up')->assertOk();
});

it('translates the response from the Accept-Language header', function (): void {
    $this->getJson('/api/v1/health', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.message', 'The service is running.')
        ->assertHeader('Content-Language', 'en');
});

it('falls back to Arabic for unsupported languages', function (): void {
    $this->getJson('/api/v1/health', ['Accept-Language' => 'fr'])
        ->assertHeader('Content-Language', 'ar');
});

it('rejects requests that do not accept JSON', function (): void {
    $this->get('/api/v1/health', ['Accept' => 'text/html'])
        ->assertStatus(406)
        ->assertJsonPath('code', 'request.not_acceptable');
});

it('sends the security headers on every API response', function (): void {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Request-Id');
});

it('denies framing through the content security policy', function (): void {
    $policy = (string) $this->getJson('/api/v1/health')->headers->get('Content-Security-Policy');

    expect($policy)->toContain("frame-ancestors 'none'")->toContain("default-src 'none'");
});

it('sends HSTS over HTTPS', function (): void {
    $this->getJson('https://localhost/api/v1/health')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('echoes a valid client request id and replaces an invalid one', function (): void {
    $requestId = '0d6f0f8e-3c1a-4e8a-9b8e-2b6f4c6d7a11';

    $this->getJson('/api/v1/health', ['X-Request-Id' => $requestId])->assertHeader('X-Request-Id', $requestId);

    expect($this->getJson('/api/v1/health', ['X-Request-Id' => 'not-a-uuid'])->headers->get('X-Request-Id'))
        ->not->toBe('not-a-uuid');
});

it('returns the error envelope with a stable code for unknown endpoints', function (): void {
    $this->getJson('/api/v1/unknown-endpoint')
        ->assertNotFound()
        ->assertExactJson([
            'data' => null,
            'meta' => [],
            'message' => 'المورد المطلوب غير موجود.',
            'code' => 'not_found',
            'errors' => [],
        ]);
});

it('rate limits the public health endpoint', function (): void {
    foreach (range(1, 120) as $attempt) {
        $this->getJson('/api/v1/health');
    }

    $this->getJson('/api/v1/health')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');
});
