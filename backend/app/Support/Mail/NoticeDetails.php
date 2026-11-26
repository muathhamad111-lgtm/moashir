<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Data\Mail\DetailRow;
use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\MailDetailTone;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\Lang;

/**
 * The details card of a notice e-mail, built from the values its template already fills: the deadline first, then
 * the amount and the reference numbers, then the rest. A value only gets a row when the template's own wording
 * uses it, so a request's entity never sees a row naming itself.
 */
final class NoticeDetails
{
    /**
     * Replacement names that make a row, in the order the card shows them.
     */
    private const array TONES = [
        'deadline' => MailDetailTone::Warning,
        'amount' => MailDetailTone::Strong,
        'number' => MailDetailTone::Reference,
        'certificate' => MailDetailTone::Reference,
        'reference' => MailDetailTone::Reference,
        'period' => MailDetailTone::Plain,
        'cycle' => MailDetailTone::Plain,
        'starts_on' => MailDetailTone::Plain,
        'ends_on' => MailDetailTone::Plain,
        'purpose' => MailDetailTone::Plain,
        'entity' => MailDetailTone::Plain,
    ];

    /**
     * The parts whose wording decides which values are shown.
     */
    private const array PARTS = ['title', 'body', 'summary'];

    /**
     * @return list<DetailRow>
     */
    public static function of(NoticeText $text, string $locale): array
    {
        $wording = self::wordingOf($text, $locale);
        $rows = [];

        foreach (self::TONES as $name => $tone) {
            $value = self::valueOf($text, $name, $locale);

            if ($value !== '' && preg_match('/:'.$name.'\b/', $wording) === 1) {
                $rows[] = new DetailRow(ScalarValue::string(__('mail.details.'.$name, [], $locale)), $value, $tone);
            }
        }

        return $rows;
    }

    private static function wordingOf(NoticeText $text, string $locale): string
    {
        $parts = array_map(
            static fn (string $part): string => ScalarValue::string(Lang::get($text->key.'.'.$part, [], $locale)),
            self::PARTS,
        );

        return implode(' ', $parts);
    }

    private static function valueOf(NoticeText $text, string $name, string $locale): string
    {
        $value = $text->replacements[$name] ?? '';

        return trim($value instanceof LocalizedText ? $value->in($locale) : $value);
    }
}
