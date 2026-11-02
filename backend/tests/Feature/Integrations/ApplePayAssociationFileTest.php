<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const ASSOCIATION_FILE = '/api/v1/settings/payment/apple-pay/association-file';

const WELL_KNOWN_ASSOCIATION = '/.well-known/apple-developer-merchantid-domain-association';

function associationContents(): string
{
    return "7B2270737049223A2246414B452D4D4F41534849522D4D45524348414E54222C2276657273696F6E223A317D\n";
}

function associationUpload(?string $contents = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent('apple-developer-merchantid-domain-association', $contents ?? associationContents())->mimeType('text/plain');
}

it('stores the uploaded association file privately and serves it byte for byte', function (): void {
    Storage::fake('local');
    $admin = systemAdmin();
    signIn($admin);

    $this->get(WELL_KNOWN_ASSOCIATION)->assertNotFound();

    $this->post(ASSOCIATION_FILE, ['file' => associationUpload()], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.set', true)
        ->assertJsonPath('data.size_bytes', strlen(associationContents()))
        ->assertJsonPath('data.sha256', hash('sha256', associationContents()))
        ->assertJsonPath('data.uploaded_by', ['id' => $admin->id, 'name' => $admin->name]);

    $files = Storage::disk('local')->allFiles('files/apple-pay-domain-association');
    expect($files)->toHaveCount(1)
        ->and(basename($files[0]))->not->toContain('apple-developer');

    $served = $this->get(WELL_KNOWN_ASSOCIATION)->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($served->getContent())->toBe(associationContents());

    $this->getJson('/api/v1/settings/payment')->assertJsonPath('data.apple_pay_association_file.set', true);
    expect(Audit::query()->where('event', AuditEvent::ApplePayFileUploaded->value)->count())->toBe(1);
});

it('replaces the previous file and removes it on request', function (): void {
    Storage::fake('local');
    signIn(systemAdmin());

    $this->post(ASSOCIATION_FILE, ['file' => associationUpload()], ['Accept' => 'application/json'])->assertCreated();
    $this->post(ASSOCIATION_FILE, ['file' => associationUpload("ABCDEF0123456789\n")], ['Accept' => 'application/json'])->assertCreated();

    expect(Storage::disk('local')->allFiles('files/apple-pay-domain-association'))->toHaveCount(1)
        ->and($this->get(WELL_KNOWN_ASSOCIATION)->getContent())->toBe("ABCDEF0123456789\n");

    $this->deleteJson(ASSOCIATION_FILE)->assertNoContent();
    $this->deleteJson(ASSOCIATION_FILE)->assertNotFound()->assertJsonPath('code', 'payment.apple_pay_file_missing');

    expect(Storage::disk('local')->allFiles('files/apple-pay-domain-association'))->toBe([]);
    $this->get(WELL_KNOWN_ASSOCIATION)->assertNotFound();
    expect(Audit::query()->where('event', AuditEvent::ApplePayFileRemoved->value)->count())->toBe(1);
});

it('accepts only a small plain-text association file', function (UploadedFile $file): void {
    Storage::fake('local');
    signIn(systemAdmin());

    $this->post(ASSOCIATION_FILE, ['file' => $file], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'binary content' => fn (): UploadedFile => UploadedFile::fake()->createWithContent('association', "\x00\x01\x02binary")->mimeType('text/plain'),
    'control characters' => fn (): UploadedFile => UploadedFile::fake()->createWithContent('association', "7B22\x1B[31m")->mimeType('text/plain'),
    'larger than 64 KB' => fn (): UploadedFile => UploadedFile::fake()->createWithContent('association', str_repeat('A', 66 * 1024))->mimeType('text/plain'),
    'image' => fn (): UploadedFile => UploadedFile::fake()->image('association.png'),
]);

it('scans the association file and refuses an infected or unscanned upload', function (): void {
    Storage::fake('local');
    signIn(systemAdmin());

    $this->post(ASSOCIATION_FILE, ['file' => associationUpload('7B22'.FakeFileScanner::TEST_SIGNATURE."\n")], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.infected');

    app(FakeFileScanner::class)->makeUnavailable();
    $this->post(ASSOCIATION_FILE, ['file' => associationUpload()], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.scan_failed');

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(StoredFile::withTrashed()->count())->toBe(2);
    $this->get(WELL_KNOWN_ASSOCIATION)->assertNotFound();
});

it('records the association file as a scanned stored file', function (): void {
    Storage::fake('local');
    $admin = systemAdmin();
    signIn($admin);

    $this->post(ASSOCIATION_FILE, ['file' => associationUpload()], ['Accept' => 'application/json'])->assertCreated();

    $file = StoredFile::query()->sole();
    expect($file->purpose)->toBe(StoredFilePurpose::ApplePayDomainAssociation)
        ->and($file->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($file->scan_engine)->toBe(FakeFileScanner::ENGINE)
        ->and($file->visibility)->toBe(FileVisibility::PublicAsset)
        ->and($file->uploaded_by_user_id)->toBe($admin->id)
        ->and($file->original_name)->toBe('apple-developer-merchantid-domain-association');
});

it('checks that the served file is reachable and identical', function (): void {
    Storage::fake('local');
    signIn(systemAdmin());
    $this->post(ASSOCIATION_FILE, ['file' => associationUpload()], ['Accept' => 'application/json'])->assertCreated();

    Http::fake(['https://localhost/.well-known/*' => Http::response(associationContents())]);

    $this->postJson(ASSOCIATION_FILE.'/check')
        ->assertOk()
        ->assertJsonPath('data', ['url' => 'https://localhost'.WELL_KNOWN_ASSOCIATION, 'reachable' => true, 'status' => 200, 'matches' => true]);
});

it('reports a different or missing published file', function (): void {
    Storage::fake('local');
    signIn(systemAdmin());

    $this->postJson(ASSOCIATION_FILE.'/check')->assertNotFound();

    $this->post(ASSOCIATION_FILE, ['file' => associationUpload()], ['Accept' => 'application/json'])->assertCreated();
    Http::fake(['https://localhost/.well-known/*' => Http::response('<html>Not found</html>', 404)]);

    $this->postJson(ASSOCIATION_FILE.'/check')->assertOk()->assertJsonPath('data.reachable', false)->assertJsonPath('data.matches', false);
});
