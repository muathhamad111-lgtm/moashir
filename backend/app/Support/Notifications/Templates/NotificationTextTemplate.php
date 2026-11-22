<?php

declare(strict_types=1);

namespace App\Support\Notifications\Templates;

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationCode;

final readonly class NotificationTextTemplate
{
    /**
     * @param  array<string, array{ar: string, en: string}>  $parts  default text per part (title, body, subject, line…)
     * @param  list<string>  $placeholders
     */
    public function __construct(
        public string $key,
        public NotificationCode $code,
        public array $parts,
        public array $placeholders,
    ) {}

    /**
     * The parts an administrator may write: the shipped ones plus the e-mail's own subject and body when the
     * notice shares its title and body with e-mail.
     *
     * @return array<string, array{ar: string, en: string}>
     */
    public function editableParts(): array
    {
        return NotificationTextParts::withEmailParts($this->parts);
    }

    /**
     * Who this text speaks to: the type's only audience, the beneficiary for an «entity…» text of a mixed type,
     * management for its other texts, or null for a text every audience receives.
     */
    public function audience(NotificationType $type): ?NotificationAudienceKind
    {
        $kinds = $type->audienceKinds();
        if (count($kinds) === 1) {
            return $kinds[0];
        }

        $variant = $this->variant();
        if ($variant === '' || ! $type->reachesKind(NotificationAudienceKind::Beneficiary) || ! in_array(NotificationAudience::Entity, $type->audiences, true)) {
            return null;
        }

        $last = substr($variant, (int) strrpos('.'.$variant, '.'));

        return str_starts_with($last, 'entity') ? NotificationAudienceKind::Beneficiary : NotificationAudienceKind::Management;
    }

    public function partKey(string $part): string
    {
        return $this->key.'.'.$part;
    }

    /**
     * What sets this text apart from the other texts of the same type: the audience or state after the code
     * («entity», «staff», «continued.entity»), empty when the type has one text, or the whole group key for
     * texts filed without a code («account_status.suspended», «files.quarantined»).
     */
    public function variant(): string
    {
        $marker = '.'.$this->code->value;
        $position = strpos($this->key, $marker.'.');

        if ($position !== false) {
            return substr($this->key, $position + strlen($marker) + 1);
        }

        return str_ends_with($this->key, $marker) ? '' : $this->key;
    }
}
