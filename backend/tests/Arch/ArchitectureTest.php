<?php

declare(strict_types=1);

use App\Contracts\Assessments\AssessmentState;
use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Contracts\Documents\PdfRenderer;
use App\Contracts\Files\FileScanner;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Resources\Award\CommitteeAssignmentRowResource;
use App\Http\Resources\Award\CommitteeEvaluationResource;
use App\Http\Resources\Award\CommitteeScoresResource;
use App\Http\Resources\Award\FilteringRowResource;
use App\Http\Resources\Award\MyCommitteeDiscussionResource;
use App\Http\Resources\Award\SecretaryRequestRowResource;
use App\Integrations\ClamAv\ClamAvFileScanner;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakeFileScanner;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\CommitteeAverage;
use App\Rules\PriceBeforeVat;
use App\Rules\VatRatePercent;
use App\Services\Identity\LocalBreachedPasswordChecker;
use App\Support\FixedPoint;
use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\SecretValue;
use App\Support\ValueObjects\VatRate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

arch()->preset()->php();
arch()->preset()->security();

arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('controllers do not query the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(DB::class);

arch('controllers are final and thin invokers of actions')
    ->expect('App\Http\Controllers')
    ->toBeFinal()
    ->toHaveSuffix('Controller');

arch('models do not depend on the HTTP layer')
    ->expect('App\Models')
    ->not->toUse('App\Http');

arch('actions are final and expose handle()')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('actions do not depend on HTTP requests or responses')
    ->expect('App\Actions')
    ->not->toUse(['App\Http\Requests', 'App\Http\Responses', JsonResponse::class]);

arch('value objects are immutable')
    ->expect('App\Support\ValueObjects')
    ->toBeReadonly()
    ->toBeFinal();

arch('data transfer objects are immutable')
    ->expect('App\Data')
    ->toBeReadonly()
    ->toBeFinal();

arch('enums are backed or pure enums only')
    ->expect('App\Enums')
    ->toBeEnums();

arch('policies are final')
    ->expect('App\Policies')
    ->toBeFinal()
    ->toHaveSuffix('Policy');

arch('form requests extend the framework form request')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class)
    ->toHaveSuffix('Request');

arch('resources extend the JSON resource')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class)
    ->toHaveSuffix('Resource');

arch('env() is only used in config files')
    ->expect('env')
    ->not->toBeUsed()
    ->ignoring('config');

arch('application code never checks permissions directly')
    ->expect('App')
    ->not->toUse(['hasPermissionTo', 'hasRole', 'hasAnyRole', 'hasAllRoles', 'hasDirectPermission']);

arch('integrations are reached through contracts')
    ->expect('App\Actions')
    ->not->toUse('App\Integrations');

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('passwords are never sent to a remote breach service')
    ->expect('App')
    ->not->toUse([UncompromisedVerifier::class, NotPwnedVerifier::class, Password::class]);

arch('the breached-password checker stays offline')
    ->expect(LocalBreachedPasswordChecker::class)
    ->not->toUse([Http::class, Factory::class]);

arch('domain events are immutable and dispatched only after the transaction commits')
    ->expect('App\Events')
    ->toBeFinal()
    ->toBeReadonly()
    ->toImplement(ShouldDispatchAfterCommit::class);

arch('listeners are final')
    ->expect('App\Listeners')
    ->toBeFinal();

arch('the criteria domain reaches the assessments domain only through contracts')
    ->expect(['App\Actions\Criteria', 'App\Services\Criteria', 'App\Http\Controllers\Api\V1\Criteria'])
    ->not->toUse(['App\Services\Assessments', 'App\Actions\Assessments']);

arch('criteria models stay free of business services')
    ->expect('App\Models\Criteria')
    ->not->toUse(['App\Services', 'App\Actions', 'App\Events']);

arch('criteria weights are never handled as floats in the domain')
    ->expect(['App\Actions\Criteria', 'App\Services\Criteria'])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil']);

arch('the identity domain reaches the entities domain only through its contract')
    ->expect(['App\Actions\Identity', 'App\Services\Identity', 'App\Http\Controllers\Api\V1\Identity'])
    ->not->toUse(['App\Models\Entities', 'App\Services\Entities', 'App\Actions\Entities']);

arch('the entities domain reaches later domains only through contracts')
    ->expect(['App\Actions\Entities', 'App\Services\Entities', 'App\Http\Controllers\Api\V1\Entities'])
    ->not->toUse(['App\Services\Assessments', 'App\Actions\Assessments', 'App\Services\Criteria', 'App\Actions\Criteria']);

arch('entity models stay free of business services')
    ->expect('App\Models\Entities')
    ->not->toUse(['App\Services', 'App\Actions', 'App\Events']);

arch('assessment models stay free of business services')
    ->expect('App\Models\Assessments')
    ->not->toUse(['App\Services', 'App\Actions', 'App\Events', 'App\Http']);

arch('scores are never handled as floats in the assessments domain')
    ->expect(['App\Actions\Assessments', 'App\Services\Assessments', 'App\Data\Assessments', FixedPoint::class, Percent::class, EarnedScore::class])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil', 'fdiv', 'bcdiv']);

arch('the assessments domain changes entities only through the entities services')
    ->expect(['App\Actions\Assessments', 'App\Services\Assessments'])
    ->not->toUse(['App\Actions\Entities', 'App\Actions\Criteria']);

arch('assessment states are path enums behind the state contract')
    ->expect([SelfAssessmentStatus::class, AccreditedAssessmentStatus::class, AdvancedAssessmentStatus::class])
    ->toImplement(AssessmentState::class);

arch('reviewed paths share the reviewed state contract')
    ->expect([AccreditedAssessmentStatus::class, AdvancedAssessmentStatus::class])
    ->toImplement(ReviewedAssessmentState::class);

arch('award models stay free of business services')
    ->expect('App\Models\Award')
    ->not->toUse(['App\Services', 'App\Actions', 'App\Events', 'App\Http']);

arch('award percentages are never handled as floats')
    ->expect(['App\Actions\Award', 'App\Services\Award', 'App\Data\Award'])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil', 'fdiv', 'bcdiv']);

arch('the criteria and identity domains reach the award domain only through contracts')
    ->expect([
        'App\Actions\Criteria', 'App\Services\Criteria', 'App\Http\Controllers\Api\V1\Criteria',
        'App\Actions\Identity', 'App\Services\Identity', 'App\Http\Controllers\Api\V1\Identity',
    ])
    ->not->toUse(['App\Services\Award', 'App\Actions\Award', 'App\Models\Award']);

arch('committee assessor resources never reach the initial assessment, other assessors or the average')
    ->expect([CommitteeEvaluationResource::class, CommitteeAssignmentRowResource::class, MyCommitteeDiscussionResource::class, CommitteeScoresResource::class])
    ->not->toUse([AssessmentResultSnapshot::class, CommitteeAverage::class, AssessmentResource::class, FilteringRowResource::class, SecretaryRequestRowResource::class]);

arch('the finance domain reaches the assessments domain only through contracts')
    ->expect(['App\Actions\Finance', 'App\Services\Finance', 'App\Http\Controllers\Api\V1\Finance'])
    ->not->toUse(['App\Services\Assessments', 'App\Actions\Assessments', 'App\Models\Assessments']);

arch('provider adapters reach providers only through the egress-checked HTTP baseline')
    ->expect('App\Integrations')
    ->not->toUse([Http::class, Factory::class]);

arch('integration resources never handle decrypted secrets')
    ->expect('App\Http\Resources\Integrations')
    ->not->toUse([SecretValue::class, ResolvedCredentials::class]);

arch('integration secrets are typed values that cannot be serialized')
    ->expect(SecretValue::class)
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveMethod('__serialize');

arch('finance and file models stay free of business services')
    ->expect(['App\Models\Finance', 'App\Models\Files'])
    ->not->toUse(['App\Services', 'App\Actions', 'App\Events', 'App\Http']);

arch('money is never handled as floats in the finance domain')
    ->expect(['App\Actions\Finance', 'App\Services\Finance', 'App\Data\Finance', Money::class, VatRate::class])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil', 'fdiv', 'number_format']);

arch('the finance and files domains reach adapters only through contracts')
    ->expect(['App\Actions\Finance', 'App\Services\Finance', 'App\Actions\Files', 'App\Services\Files', 'App\Jobs\Files', 'App\Actions\Settings', 'App\Services\Settings'])
    ->not->toUse('App\Integrations');

arch('finance settings never handle prices or the VAT rate as floats')
    ->expect(['App\Actions\Settings', 'App\Services\Settings', 'App\Data\Settings', PriceBeforeVat::class, VatRatePercent::class])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil', 'fdiv', 'number_format']);

arch('file scanners and PDF renderers implement their contracts')
    ->expect([ClamAvFileScanner::class, FakeFileScanner::class])
    ->toImplement(FileScanner::class)
    ->and([UnavailablePdfRenderer::class, FakePdfRenderer::class])
    ->toImplement(PdfRenderer::class);
