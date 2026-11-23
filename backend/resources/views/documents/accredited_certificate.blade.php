@extends('documents.layout')

@php
    $level = $data['level'] ?? null;
    $titleKey = $level === null ? 'documents.certificate.participation_title' : 'documents.certificate.title';
    $entityAr = $data['entity_name']['ar'] ?? '';
    $entityEn = $data['entity_name']['en'] ?? null;
@endphp

@section('title', __($titleKey, [], 'ar').' — '.($data['number'] ?? ''))

@section('styles')
@include('documents.partials.certificate_styles')
@endsection

@section('content')
<div class="certificate certificate--{{ $level ?? 'participation' }}">
  <div class="certificate__frame">
    <div class="certificate__inner">
      <header class="certificate__head">
        <img class="certificate__logo" src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt', [], 'ar') }}">
        @if ($level !== null)
          <div class="certificate__seal">
            <img src="{{ $assets->seal($level) }}" alt="{{ __('documents.certificate.seal_alt', ['level' => $data['level_label']['ar'] ?? ''], 'ar') }}">
            <span class="certificate__seal-year" aria-hidden="true">{{ $data['issue_year'] ?? '' }}</span>
          </div>
        @else
          <img class="certificate__star" src="{{ $assets->star() }}" alt="">
        @endif
      </header>

      <main class="certificate__body">
        <h1 class="certificate__title">{{ __($titleKey, [], 'ar') }}</h1>
        <p class="certificate__title-en" lang="en" dir="ltr">{{ __($titleKey, [], 'en') }}</p>
        <div class="certificate__rule" aria-hidden="true"></div>
        <p class="certificate__lead">{{ __('documents.certificate.certifies', [], 'ar') }}</p>
        <p class="certificate__entity">{{ $entityAr }}</p>
        @if ($entityEn !== null)
          <p class="certificate__entity-en" lang="en" dir="ltr">{{ $entityEn }}</p>
        @endif
        <p class="certificate__statement">
          {{ __('documents.certificate.completed', [], 'ar') }}
          {{ __('documents.certificate.achieved', [], 'ar') }}
          <bdi class="certificate__percent ltr" dir="ltr">{{ $data['percent'] ?? '' }}%</bdi>@if ($level !== null)،
          {{ __('documents.certificate.level', [], 'ar') }} <strong>{{ $data['level_label']['ar'] ?? '' }}</strong>@endif.
        </p>
        <p class="certificate__statement-en" lang="en" dir="ltr">
          {{ __('documents.certificate.certifies', [], 'en') }} <bdi>{{ $entityEn ?? $entityAr }}</bdi>
          {{ __('documents.certificate.completed', [], 'en') }} {{ __('documents.certificate.achieved', [], 'en') }}
          {{ $data['percent'] ?? '' }}%@if ($level !== null), {{ __('documents.certificate.level', [], 'en') }} {{ $data['level_label']['en'] ?? '' }}@endif.
        </p>
      </main>

      @include('documents.partials.certificate_foot')
    </div>
  </div>
</div>
@endsection
