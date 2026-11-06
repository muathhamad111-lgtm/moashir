<?php

declare(strict_types=1);

use App\Contracts\Files\ClamdConnector;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Files\FileScannerUnavailable;
use App\Jobs\Platform\RecordQueueHeartbeat;
use App\Services\Platform\Readiness\QueueHeartbeat;
use Illuminate\Console\Scheduling\Schedule;

const READINESS = '/api/v1/health/ready';

function unreachableClamd(): void
{
    app()->instance(ClamdConnector::class, new class implements ClamdConnector
    {
        public function connect(): never
        {
            throw FileScannerUnavailable::unreachable();
        }
    });
}

it('answers ready with the status only to an anonymous caller', function (): void {
    $this->getJson(READINESS)
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ready'], 'meta' => [], 'errors' => null]);
});

it('gives the per-check details to an allow-listed address without any internals', function (): void {
    config()->set('moashir.security.health_allowed_ips', ['10.9.0.0/24']);

    $checks = $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.15'])->getJson(READINESS)
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonStructure(['data' => ['status', 'checks', 'checked_at']])
        ->json('data.checks');

    expect($checks)->toBe([
        ['name' => 'database', 'critical' => true, 'status' => 'ok'],
        ['name' => 'redis', 'critical' => true, 'status' => 'skipped'],
        ['name' => 'queue', 'critical' => true, 'status' => 'skipped'],
        ['name' => 'disk_space', 'critical' => true, 'status' => 'ok'],
        ['name' => 'malware_scanner', 'critical' => false, 'status' => 'skipped'],
        ['name' => 'pdf_renderer', 'critical' => false, 'status' => 'skipped'],
    ]);
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.1.15'])->getJson(READINESS)->assertJsonMissingPath('data.checks');
});

it('gives the details to a signed-in System Administrator only', function (): void {
    signIn(staffUser(RoleKey::Assessor))->getJson(READINESS)->assertOk()->assertJsonMissingPath('data.checks');

    $this->flushSession();
    app('auth')->forgetGuards();
    signIn(systemAdmin())->getJson(READINESS)->assertOk()->assertJsonPath('data.checks.0.name', 'database');
});

it('reports not ready with 503 when a critical check fails', function (): void {
    config()->set('moashir.health.min_free_disk_mb', 1000000000);

    $this->getJson(READINESS)
        ->assertStatus(503)
        ->assertExactJson(['data' => ['status' => 'not_ready'], 'meta' => [], 'errors' => null]);
});

it('reports degraded but serving when only the malware scanner or the PDF renderer fails', function (): void {
    config()->set('moashir.files.scanner', 'clamav');
    config()->set('moashir.documents.pdf_renderer', 'unavailable');
    config()->set('moashir.security.health_allowed_ips', ['127.0.0.1']);
    unreachableClamd();

    $this->getJson(READINESS)
        ->assertOk()
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.checks.4', ['name' => 'malware_scanner', 'critical' => false, 'status' => 'failing'])
        ->assertJsonPath('data.checks.5', ['name' => 'pdf_renderer', 'critical' => false, 'status' => 'failing']);
});

it('reads the queue heartbeat written by the scheduled job', function (): void {
    config()->set('queue.default', 'database');
    config()->set('moashir.security.health_allowed_ips', ['127.0.0.1']);

    $this->getJson(READINESS)->assertStatus(503)->assertJsonPath('data.checks.2.status', 'failing');

    new RecordQueueHeartbeat()->handle(app(QueueHeartbeat::class));
    $this->getJson(READINESS)->assertOk()->assertJsonPath('data.checks.2.status', 'ok');

    $this->travel(6)->minutes();
    $this->getJson(READINESS)->assertStatus(503)->assertJsonPath('data.checks.2.status', 'failing');
});

it('schedules the queue heartbeat every minute and the backup monitor daily', function (): void {
    $events = collect(app(Schedule::class)->events());

    expect($events->first(static fn ($event): bool => str_contains((string) $event->description, RecordQueueHeartbeat::class))?->expression)->toBe('* * * * *')
        ->and($events->first(static fn ($event): bool => str_contains((string) $event->command, 'backup:monitor'))?->expression)->toBe('0 6 * * *');
});
