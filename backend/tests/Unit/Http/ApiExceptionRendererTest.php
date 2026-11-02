<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;

it('hides unexpected errors behind a generic translated message in production mode', function (): void {
    config()->set('app.debug', false);

    $response = app(ApiExceptionRenderer::class)->render(new RuntimeException('SQLSTATE connection to db-host failed'));

    expect($response?->getStatusCode())->toBe(500)
        ->and($response?->getData(true))->toBe([
            'data' => null,
            'meta' => [],
            'message' => 'حدث خطأ غير متوقع. يرجى المحاولة لاحقًا.',
            'code' => 'server_error',
            'errors' => [],
        ]);
});

it('leaves unexpected errors to the debug renderer in local debugging', function (): void {
    config()->set('app.debug', true);

    expect(app(ApiExceptionRenderer::class)->render(new RuntimeException('boom')))->toBeNull();
});
