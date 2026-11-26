<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Data\Notifications\NotificationTextChange;
use App\Rules\FreeOfHtmlMarkup;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\Templates\ShippedNotificationTemplates;
use App\Support\Notifications\Templates\NotificationTextParts;
use App\Support\Notifications\Templates\NotificationTextTemplate;
use App\Support\Notifications\Templates\TemplatePlaceholders;
use App\Support\ScalarValue;
use App\Support\Sms\SmsLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Text rules: only the parts the template ships with, plus the e-mail's own subject and body and the SMS; both
 * languages filled; only the template's own variables; an SMS within three messages. A core type keeps every
 * variable its shipped text uses (links, codes and durations must stay), keeps its button as shipped and takes
 * no SMS text, since its channels never change.
 */
final class SaveNotificationTextRequest extends FormRequest
{
    private const int PART_MAX = 2000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $parts = [...array_keys($this->template()->editableParts()), NotificationTextParts::SMS];

        return [
            'parts' => ['required', 'array:'.implode(',', $parts)],
            'parts.*' => ['required', 'array:ar,en'],
            'parts.*.ar' => ['required', 'string', 'max:'.self::PART_MAX, new FreeOfHtmlMarkup],
            'parts.*.en' => ['required', 'string', 'max:'.self::PART_MAX, new FreeOfHtmlMarkup],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->checkCoreRules(...), $this->checkPlaceholders(...), $this->checkSmsLength(...)];
    }

    public function template(): NotificationTextTemplate
    {
        return app(ShippedNotificationTemplates::class)->catalog()->find(ScalarValue::string($this->route('templateKey'))) ?? abort(404);
    }

    public function change(): NotificationTextChange
    {
        $parts = [];

        foreach ($this->submittedParts() as $part => $text) {
            $parts[$part] = ['ar' => trim(ScalarValue::string($text['ar'] ?? null)), 'en' => trim(ScalarValue::string($text['en'] ?? null))];
        }

        return new NotificationTextChange($this->template(), $parts);
    }

    /**
     * @return array<string, array<array-key, mixed>>
     */
    private function submittedParts(): array
    {
        $parts = $this->input('parts');

        return is_array($parts) ? array_filter(array_combine(array_map(strval(...), array_keys($parts)), $parts), is_array(...)) : [];
    }

    private function checkCoreRules(Validator $validator): void
    {
        if (! $this->isCore()) {
            return;
        }

        foreach (array_keys($this->submittedParts()) as $part) {
            if ($part === NotificationTextParts::SMS) {
                $validator->errors()->add('parts.'.$part, __('validation.custom.notification_channels.core'));
            }
            if (NotificationTextParts::isFixed($part, true)) {
                $validator->errors()->add('parts.'.$part, __('validation.custom.notification_text.fixed_part'));
            }
        }
    }

    private function checkPlaceholders(Validator $validator): void
    {
        $template = $this->template();
        $defaults = $template->editableParts();
        $isCore = $this->isCore();

        foreach ($this->submittedParts() as $part => $text) {
            foreach (['ar', 'en'] as $locale) {
                $value = ScalarValue::string($text[$locale] ?? null);
                $required = $isCore ? TemplatePlaceholders::in($defaults[$part][$locale] ?? '') : [];
                foreach (TemplatePlaceholders::unknownIn($value, $template->placeholders) as $unknown) {
                    $validator->errors()->add("parts.{$part}.{$locale}", __('validation.custom.notification_text.unknown_variable', ['variable' => $unknown]));
                }
                foreach (TemplatePlaceholders::missingFrom($value, $required) as $missing) {
                    $validator->errors()->add("parts.{$part}.{$locale}", __('validation.custom.notification_text.required_variable', ['variable' => $missing]));
                }
            }
        }
    }

    private function checkSmsLength(Validator $validator): void
    {
        $sms = $this->submittedParts()[NotificationTextParts::SMS] ?? [];

        foreach (['ar', 'en'] as $locale) {
            if (SmsLength::segments(trim(ScalarValue::string($sms[$locale] ?? null))) > SmsLength::MAX_SEGMENTS) {
                $validator->errors()->add('parts.sms.'.$locale, __('validation.custom.notification_text.sms_too_long', ['count' => SmsLength::MAX_SEGMENTS]));
            }
        }
    }

    private function isCore(): bool
    {
        return app(NotificationTypeCatalog::class)->of($this->template()->code)->isCore;
    }
}
