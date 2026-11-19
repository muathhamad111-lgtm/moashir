<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Casts\LocalizedContentCast;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property list<array{platform: string, url: string}> $social
 * @property array<string, mixed>|null $contact
 * @property LocalizedContent|null $footer_note
 * @property list<array{label: array<string, string>, url: string, is_visible?: bool}> $quick_links
 * @property list<array{label: array<string, string>, url: string|null, is_visible?: bool, children?: list<array{label: array<string, string>, url: string, is_visible?: bool}>}> $header_links
 * @property LocalizedContent|null $platform_name
 * @property LocalizedContent|null $platform_description
 * @property array<string, mixed>|null $seo
 * @property int $featured_kpi_limit
 * @property bool $show_featured_kpis
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SiteSettings extends Model
{
    public const int SINGLETON_ID = 1;

    public $incrementing = false;

    protected $table = 'site_settings';

    /**
     * @var list<string>
     */
    protected $fillable = ['platform_name', 'platform_description', 'social', 'contact', 'footer_note', 'quick_links', 'header_links', 'seo', 'featured_kpi_limit', 'show_featured_kpis'];

    public static function current(): self
    {
        return self::query()->findOrFail(self::SINGLETON_ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_name' => LocalizedContentCast::class,
            'platform_description' => LocalizedContentCast::class,
            'social' => 'array',
            'contact' => 'array',
            'footer_note' => LocalizedContentCast::class,
            'quick_links' => 'array',
            'header_links' => 'array',
            'seo' => 'array',
            'featured_kpi_limit' => 'integer',
            'show_featured_kpis' => 'boolean',
        ];
    }
}
