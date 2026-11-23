@extends('documents.layout')

@php
    $kind = in_array($data['kind'] ?? null, ['advanced_winner', 'advanced_qualified', 'advanced_participation'], true) ? $data['kind'] : 'advanced_participation';
    $titleKey = 'documents.award_certificate.titles.'.$kind;
    $entityAr = $data['entity_name']['ar'] ?? '';
    $entityEn = $data['entity_name']['en'] ?? null;
    $text = static fn (?array $value, string $locale): string => (string) ($value[$locale] ?? $value['ar'] ?? '');
    $rank = static fn (mixed $value, string $locale): string => in_array($value, [1, 2, 3], true) ? __('documents.award_certificate.ranks.'.$value, [], $locale) : '';
    $cycle = static fn (string $locale): string => trim($text($data['cycle_name'] ?? null, $locale).' '.($data['cycle_year'] ?? ''));
    $statement = static function (string $locale) use ($data, $kind, $text, $rank, $cycle): string {
        $replace = [
            'cycle' => $cycle($locale),
            'region' => $text($data['region'] ?? null, $locale),
            'rank' => $rank($data['regional_rank'] ?? null, $locale),
            'national_rank' => $rank($data['national_rank'] ?? null, $locale),
        ];
        $key = match ($kind) {
            'advanced_winner' => $replace['national_rank'] !== '' ? 'national_winner' : 'regional_winner',
            'advanced_qualified' => 'qualified',
            default => 'participation',
        };

        return __('documents.award_certificate.statements.'.$key, $replace, $locale);
    };
@endphp

@section('title', __($titleKey, [], 'ar').' — '.($data['number'] ?? ''))

@section('styles')
@include('documents.partials.certificate_styles')
@endsection

@section('content')
<div class="certificate certificate--award certificate--{{ $kind }}">
  <div class="certificate__frame">
    <div class="certificate__inner">
      <header class="certificate__head">
        <img class="certificate__logo" src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt', [], 'ar') }}">
        <img class="certificate__star" src="{{ $assets->star() }}" alt="">
      </header>

      <main class="certificate__body">
        <h1 class="certificate__title">{{ __($titleKey, [], 'ar') }}</h1>
        <p class="certificate__title-en" lang="en" dir="ltr">{{ __($titleKey, [], 'en') }}</p>
        <div class="certificate__rule" aria-hidden="true"></div>
        <p class="certificate__lead">{{ __('documents.award_certificate.leads.'.$kind, [], 'ar') }}</p>
        <p class="certificate__entity">{{ $entityAr }}</p>
        @if ($entityEn !== null)
          <p class="certificate__entity-en" lang="en" dir="ltr">{{ $entityEn }}</p>
        @endif
        <p class="certificate__statement">{{ $statement('ar') }}</p>
        <p class="certificate__statement-en" lang="en" dir="ltr">
          {{ __('documents.award_certificate.leads.'.$kind, [], 'en') }} <bdi>{{ $entityEn ?? $entityAr }}</bdi> {{ $statement('en') }}
        </p>
      </main>

      @include('documents.partials.certificate_foot')
    </div>
  </div>
</div>
@endsection
