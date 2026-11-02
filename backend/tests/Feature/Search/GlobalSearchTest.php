<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use Illuminate\Testing\TestResponse;

function namedStaffUser(string $name, string $email, RoleKey $role = RoleKey::Assessor): User
{
    $user = staffUser($role);
    $user->forceFill(['name' => $name, 'email' => $email])->save();

    return $user;
}

/**
 * @return list<mixed>
 */
function namedSearcher(): User
{
    return namedStaffUser('سارة الشهري', 'sara.alshehri@moashir.example.sa', RoleKey::SystemAdmin);
}

function searchGroupTypes(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('type')->all();
}

it('returns grouped results with the envelope for a system administrator', function (): void {
    $target = namedStaffUser('أحمد العتيبي', 'ahmed.alotaibi@moashir.example.sa');
    namedStaffUser('نورة القحطاني', 'noura.alqahtani@moashir.example.sa');
    signIn(namedSearcher());

    $response = $this->getJson('/api/v1/search?q='.urlencode('أحمد'))
        ->assertOk()
        ->assertJsonPath('errors', null)
        ->assertJsonPath('meta.limit', 5)
        ->assertJsonPath('data.2.type', 'users')
        ->assertJsonPath('data.2.total', 1)
        ->assertJsonPath('data.2.items.0', [
            'id' => $target->id,
            'name' => 'أحمد العتيبي',
            'email' => 'ahmed.alotaibi@moashir.example.sa',
            'status' => 'active',
            'roles' => ['assessor'],
        ]);

    expect(searchGroupTypes($response))->toBe(['entities', 'requests', 'users'])
        ->and($response->json('data.0'))->toBe(['type' => 'entities', 'total' => 0, 'items' => []])
        ->and($response->json('data.1'))->toBe(['type' => 'requests', 'total' => 0, 'items' => []]);
});

it('matches Arabic names regardless of hamza forms, diacritics, tatweel and final letters', function (string $query): void {
    namedStaffUser('أحمد العتيبي', 'ahmed.alotaibi@moashir.example.sa');
    namedStaffUser('فاطمة الزهراني', 'fatimah.alzahrani@moashir.example.sa');
    signIn(namedSearcher());

    $this->getJson('/api/v1/search?q='.urlencode($query))
        ->assertOk()
        ->assertJsonPath('data.2.total', 1);
})->with([
    'without hamza' => ['احمد'],
    'with diacritics' => ['أَحْمَد'],
    'with tatweel' => ['أحـــمد'],
    'alef maqsura for yaa' => ['العتيبى'],
    'haa for taa marbuta' => ['فاطمه'],
]);

it('matches staff users by e-mail regardless of letter case', function (): void {
    namedStaffUser('أحمد العتيبي', 'ahmed.alotaibi@moashir.example.sa');
    signIn(namedSearcher());

    $this->getJson('/api/v1/search?q=AHMED.ALOTAIBI')->assertOk()->assertJsonPath('data.2.total', 1);
});

it('treats LIKE wildcards in the term literally', function (): void {
    namedStaffUser('أحمد العتيبي', 'ahmed.alotaibi@moashir.example.sa');
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?q='.urlencode('%_'))->assertOk()->assertJsonPath('data.2.total', 0);
});

it('never returns Super Admin accounts, not even to a Super Admin', function (Closure $searcher): void {
    $hidden = superAdmin();
    $hidden->forceFill(['name' => 'أحمد المالكي'])->save();
    $visible = namedStaffUser('أحمد العتيبي', 'ahmed.alotaibi@moashir.example.sa');
    $actor = $searcher();
    $actor->forceFill(['name' => 'سارة الشهري'])->save();
    signIn($actor);

    $response = $this->getJson('/api/v1/search?q='.urlencode('أحمد'))->assertOk();

    expect(searchGroupTypes($response))->toBe(['entities', 'requests', 'users'])
        ->and($response->json('data.2.total'))->toBe(1)
        ->and(collect($response->json('data.2.items'))->pluck('id')->all())->toBe([$visible->id]);
})->with([
    'system administrator' => [systemAdmin(...)],
    'super admin' => [superAdmin(...)],
]);

it('never returns entity accounts in the users group', function (): void {
    entityUser()->forceFill(['name' => 'أحمد الشهري'])->save();
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?q='.urlencode('أحمد'))->assertOk()->assertJsonPath('data.2.total', 0);
});

it('caps the items per group, orders them by name and reports the full total', function (): void {
    namedStaffUser('سارة الدوسري', 'sarah.aldosari@moashir.example.sa');
    namedStaffUser('سارة البقمي', 'sarah.albogami@moashir.example.sa');
    namedStaffUser('سارة الحربي', 'sarah.alharbi@moashir.example.sa');
    signIn(systemAdmin());

    $response = $this->getJson('/api/v1/search?q='.urlencode('سارة').'&limit=2')
        ->assertOk()
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('data.2.total', 3)
        ->assertJsonCount(2, 'data.2.items');

    expect(collect($response->json('data.2.items'))->pluck('name')->all())->toBe(['سارة البقمي', 'سارة الحربي']);
});

it('returns only the groups the signed-in user may see', function (RoleKey $role, array $groups): void {
    namedStaffUser('أحمد العتيبي', 'ahmed.alotaibi@moashir.example.sa');
    signIn(staffUser($role));

    expect(searchGroupTypes($this->getJson('/api/v1/search?q='.urlencode('أحمد'))->assertOk()))->toBe($groups);
})->with([
    'assessor' => [RoleKey::Assessor, ['entities', 'requests']],
    'requests follower' => [RoleKey::RequestFollower, ['entities', 'requests']],
    'finance officer' => [RoleKey::FinanceOfficer, ['entities']],
    'system administrator' => [RoleKey::SystemAdmin, ['entities', 'requests', 'users']],
]);

it('denies users who hold no searchable permission', function (Closure $user): void {
    signIn($user());

    $this->getJson('/api/v1/search?q='.urlencode('أحمد'))->assertForbidden()->assertJsonPath('code', 'forbidden');
})->with([
    'content officer' => [fn (): User => staffUser(RoleKey::ContentOfficer)],
    'criteria officer' => [fn (): User => staffUser(RoleKey::CriteriaOfficer)],
    'entity user' => [entityUser(...)],
]);

it('requires authentication', function (): void {
    $this->getJson('/api/v1/search?q='.urlencode('أحمد'))->assertUnauthorized();
});

it('blocks users who must change their password first', function (): void {
    $administrator = systemAdmin();
    $administrator->forceFill(['password_change_required' => true])->save();
    signIn($administrator);

    $this->getJson('/api/v1/search?q='.urlencode('أحمد'))->assertForbidden();
});

it('validates the search query', function (string $query, string $field): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?'.$query)->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);
})->with([
    'missing term' => ['', 'q'],
    'blank term' => ['q=%20%20', 'q'],
    'one character' => ['q=a', 'q'],
    'one Arabic letter with diacritics' => ['q='.urlencode('أَ'), 'q'],
    'marks and tatweel only' => ['q='.urlencode('ـــَ'), 'q'],
    'term too long' => ['q='.str_repeat('a', 101), 'q'],
    'term as an array' => ['q[]=ab', 'q'],
    'limit below one' => ['q=ab&limit=0', 'limit'],
    'limit above ten' => ['q=ab&limit=11', 'limit'],
    'limit not a number' => ['q=ab&limit=many', 'limit'],
]);

it('explains a too-short term in the request language', function (string $locale, string $message): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?q=a', ['Accept-Language' => $locale])
        ->assertUnprocessable()
        ->assertJsonPath('errors.q.0', $message);
})->with([
    'English' => ['en', 'The search term must contain at least 2 letters or digits.'],
    'Arabic' => ['ar', 'يجب أن تحتوي عبارة البحث على 2 من الحروف أو الأرقام على الأقل.'],
]);

it('rate limits searches per user', function (): void {
    signIn(systemAdmin());

    foreach (range(1, 60) as $attempt) {
        $this->getJson('/api/v1/search?q=ab')->assertOk();
    }

    $this->getJson('/api/v1/search?q=ab')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});
