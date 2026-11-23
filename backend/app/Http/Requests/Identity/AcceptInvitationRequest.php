<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\InvitationAcceptance;
use App\Models\Identity\TermsVersion;
use App\Rules\SaudiNationalId;
use App\Services\Identity\PasswordPolicy;
use App\Services\Nda\InvitationNda;
use App\Support\ScalarValue;
use App\Support\ValueObjects\NationalId;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptInvitationRequest extends FormRequest
{
    private ?TermsVersion $requiredNda = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(PasswordPolicy $policy, InvitationNda $nda): array
    {
        $this->requiredNda = $nda->forToken(ScalarValue::string($this->route('token')));

        return [
            'password' => $policy->rules(),
            'terms_version_id' => [
                $this->currentTerms() instanceof TermsVersion ? 'required' : 'nullable',
                'integer',
                $this->mustBeCurrentTerms(...),
            ],
            'national_id' => ['nullable', 'string', new SaudiNationalId],
            'nda_version_id' => [
                $this->requiredNda instanceof TermsVersion ? 'required' : 'nullable',
                'integer',
                $this->mustBeRequiredNda(...),
            ],
        ];
    }

    public function acceptance(): InvitationAcceptance
    {
        return new InvitationAcceptance(
            $this->string('password')->toString(),
            $this->currentTerms(),
            $this->filled('national_id') ? new NationalId($this->string('national_id')->toString()) : null,
            $this->requiredNda,
        );
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private function mustBeCurrentTerms(string $attribute, mixed $value, Closure $fail): void
    {
        $current = $this->currentTerms();

        if ($current instanceof TermsVersion && ScalarValue::int($value) !== $current->id) {
            $fail(__('validation.custom.terms_version_id.not_current'));
        }
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private function mustBeRequiredNda(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->requiredNda instanceof TermsVersion && ScalarValue::int($value) !== $this->requiredNda->id) {
            $fail(__('errors.nda.version_not_current'));
        }
    }

    private function currentTerms(): ?TermsVersion
    {
        return TermsVersion::query()->current()->where('kind', 'terms')->first();
    }
}
