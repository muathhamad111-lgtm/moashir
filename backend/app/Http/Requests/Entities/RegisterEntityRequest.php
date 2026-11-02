<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityRegistrationData;
use App\Enums\Identity\Locale;
use App\Enums\Identity\TermsKind;
use App\Exceptions\Entities\RegistrationRejected;
use App\Models\Identity\TermsVersion;
use App\Services\Identity\CurrentTermsVersions;
use App\Services\Identity\PasswordPolicy;
use App\Support\Entities\EntityProfileInput;
use App\Support\ScalarValue;
use App\Support\ValueObjects\EmailAddress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterEntityRequest extends FormRequest
{
    private ?TermsVersion $currentTerms = null;

    private ?TermsVersion $currentPrivacy = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(PasswordPolicy $passwords, CurrentTermsVersions $terms): array
    {
        $this->currentTerms = $terms->of(TermsKind::Terms) ?? throw RegistrationRejected::termsUnavailable();
        $this->currentPrivacy = $terms->of(TermsKind::Privacy);

        return [
            ...EntityProfileInput::rules($this->input('region_id')),
            'official_email' => ['required', 'string', 'email:filter', 'max:254'],
            'password' => $passwords->rules(),
            'terms_version_id' => ['required', 'integer', $this->mustBe($this->currentTerms, 'terms_version_id')],
            'privacy_version_id' => [$this->currentPrivacy instanceof TermsVersion ? 'required' : 'prohibited', 'integer', $this->mustBe($this->currentPrivacy, 'privacy_version_id')],
            'terms_accepted' => ['accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function registration(): EntityRegistrationData
    {
        $validated = $this->validated();

        return new EntityRegistrationData(
            EntityProfileInput::profile($validated),
            EntityProfileInput::contact($validated),
            new EmailAddress(ScalarValue::string($validated['official_email'] ?? null)),
            $this->string('password')->toString(),
            $this->acceptedTermsVersionIds(),
            $this->boolean('marketing_consent'),
            Locale::tryFrom(app()->getLocale()) ?? Locale::Arabic,
        );
    }

    /**
     * @return list<int>
     */
    private function acceptedTermsVersionIds(): array
    {
        return array_values(array_map(
            static fn (TermsVersion $version): int => $version->id,
            array_filter([$this->currentTerms, $this->currentPrivacy]),
        ));
    }

    private function mustBe(?TermsVersion $current, string $field): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($current, $field): void {
            if ($current instanceof TermsVersion && ScalarValue::int($value) !== $current->id) {
                $fail(__('validation.custom.'.$field.'.not_current'));
            }
        };
    }
}
