@extends('documents.layout')

@php
    $locale = $page->locale;
    $text = static fn (?array $value): string => (string) ($value[$locale] ?? $value['ar'] ?? '');
    $result = $data['result'] ?? [];
    $change = $data['change'] ?? [];
    $standing = $data['certificate']['standing'] ?? 'not_issued';
    $excerpt = $data['assessor_summary_excerpt'] ?? null;
    $profile = $data['profile'] ?? ['classes' => [], 'bands' => []];
@endphp

@section('title', __('insights.board.title').' — '.($result['number'] ?? ''))

@section('styles')
@page { size: 210mm 297mm; margin: 9mm 11mm 9mm; }
body { font-size: 8.6pt; line-height: 1.38; }
.board__head { display: flex; align-items: center; justify-content: space-between; gap: 6mm; padding-bottom: 2.5mm; border-bottom: 0.6mm solid var(--color-primary); }
.board__title h1 { font-weight: 900; font-size: 17pt; }
.board__title p { margin: 0; color: var(--color-teal-deep); font-weight: 600; font-size: 9pt; }
.board__logos { display: flex; align-items: center; gap: 4mm; }
.board__logos img { height: 12mm; max-width: 34mm; object-fit: contain; }
.board__facts { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8mm 6mm; margin: 2.5mm 0; font-size: 8.6pt; }
.board__facts div { display: flex; gap: 2mm; }
.board__facts dt { color: var(--color-muted); min-width: 30mm; }
.board__facts dd { margin: 0; font-weight: 600; }
.board__score { display: grid; grid-template-columns: 1.1fr 1fr 1.2fr; gap: 3mm; }
.board__card { padding: 2.2mm 3mm; border: 0.3mm solid var(--color-border); border-radius: 2mm; background: var(--color-surface); }
.board__card--dark { background: var(--color-dark); color: var(--color-white); border-color: var(--color-dark); }
.board__card h3 { font-family: var(--font-body); font-size: 8pt; font-weight: 600; color: var(--color-muted); }
.board__card--dark h3 { color: var(--color-secondary); }
.board__big { font-family: var(--font-latin); font-weight: 700; font-size: 18pt; color: var(--color-primary); }
.board__value { font-weight: 700; font-size: 11pt; }
.board__small { font-size: 8pt; color: var(--color-muted); }
.board__card--dark .board__small { color: var(--color-secondary); }
h2.board__section { margin: 2.2mm 0 1.1mm; padding-inline-start: 2mm; border-inline-start: 1.2mm solid var(--color-teal-deep); font-weight: 700; font-size: 10.5pt; }
.board__summary { margin: 0; padding: 1.6mm 2.6mm; border: 0.3mm solid var(--color-border); border-radius: 2mm; white-space: pre-line; font-size: 8.2pt; }
.board__pointer { color: var(--color-muted); font-size: 8pt; }
.board__columns { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
.board__item { margin: 0 0 1mm; padding: 1.2mm 2.2mm; border: 0.3mm solid var(--color-border); border-radius: 1.6mm; break-inside: avoid; }
.board__item-head { display: flex; justify-content: space-between; gap: 2mm; font-weight: 700; font-size: 8.6pt; }
.board__item-head .num { font-family: var(--font-latin); white-space: nowrap; }
.board__item p { margin: 0.4mm 0 0; font-size: 7.8pt; color: var(--color-dark); }
.board__none { margin: 0; color: var(--color-muted); font-size: 8.4pt; }
.board__profile { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; font-size: 8.4pt; }
.board__counts { display: flex; flex-wrap: wrap; gap: 1.6mm; margin-top: 0.8mm; }
.board__count { padding: 0.6mm 2.4mm; border-radius: 6mm; background: var(--color-secondary); font-weight: 600; }
.board__bars { width: 100%; border-collapse: collapse; font-size: 8.4pt; }
.board__bars td { padding: 0.55mm 1.4mm; border-bottom: 0.2mm solid var(--color-border); vertical-align: middle; }
.board__bars .code { width: 9mm; font-family: var(--font-latin); white-space: nowrap; }
.board__bars .name { width: 58mm; }
.board__bars .track { position: relative; height: 3mm; background: var(--color-surface); border: 0.2mm solid var(--color-border); }
.board__bars .fill { position: absolute; inset-block: 0; inset-inline-start: 0; background: var(--color-teal-deep); }
.board__bars .num { width: 14mm; font-family: var(--font-latin); text-align: center; white-space: nowrap; }
.board__bars .band { width: 18mm; color: var(--color-teal-deep); font-weight: 600; }
.board__footer { margin-top: 1.8mm; padding-top: 1.2mm; border-top: 0.3mm solid var(--color-border); font-size: 7.6pt; color: var(--color-muted); }
.board__footer strong { color: var(--color-dark); }
@endsection

@section('content')
<header class="board__head">
  <div class="board__title">
    <h1>{{ __('insights.board.title') }}</h1>
    <p>{{ __('insights.board.subtitle') }} — {{ __('insights.board.confidential') }}</p>
  </div>
  <div class="board__logos">
    @if (($data['entity_logo'] ?? null) !== null)
      <img src="{{ $data['entity_logo'] }}" alt="{{ __('insights.board.logo_alt') }}">
    @endif
    <img src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt') }}">
  </div>
</header>

<dl class="board__facts">
  <div><dt>{{ __('insights.board.entity') }}</dt><dd dir="auto">{{ $text($data['entity']['name'] ?? null) }}</dd></div>
  <div><dt>{{ __('insights.board.request') }}</dt><dd class="ltr latin">{{ $result['number'] ?? '' }}</dd></div>
  <div><dt>{{ __('insights.board.verified_on') }}</dt><dd>@if (($data['verified_on'] ?? null) !== null)@include('documents.partials.dates', ['gregorian' => $data['verified_on'], 'hijri' => $data['verified_on_hijri'][$locale] ?? '', 'locale' => $locale])@endif</dd></div>
  <div><dt>{{ __('insights.board.criteria_version') }}</dt><dd>{{ $text($data['version_label'] ?? null) }}</dd></div>
</dl>

<section class="board__score">
  <div class="board__card board__card--dark">
    <h3>{{ __('insights.board.overall') }}</h3>
    <bdi class="board__big" dir="ltr">{{ $data['score']['percent'] ?? '' }}%</bdi>
    <div class="board__small"><bdi class="latin" dir="ltr">{{ $data['score']['earned'] ?? '' }} / {{ $data['score']['total_weight'] ?? '' }}</bdi></div>
  </div>
  <div class="board__card">
    <h3>{{ __('insights.board.certificate') }}</h3>
    <div class="board__value">
      @if ($standing === 'level'){{ $text($data['level_label'] ?? null) }}@elseif ($standing === 'participation'){{ __('insights.board.participation') }}@else{{ __('insights.board.not_issued') }}@endif
    </div>
  </div>
  <div class="board__card">
    <h3>{{ __('insights.board.change') }}</h3>
    @if (($change['delta_pp'] ?? null) !== null)
      <div class="board__value"><bdi class="latin" dir="ltr">{{ $change['delta_pp'] }}</bdi> {{ __('insights.board.points') }}</div>
      <div class="board__small">{{ __('insights.board.previous', ['number' => $change['previous']['number'] ?? '']) }}@if ($change['versions_differ'] ?? false) — {{ __('insights.delta.versions_differ') }}@endif</div>
    @else
      <div class="board__small">{{ __('insights.delta.none') }}</div>
    @endif
  </div>
</section>

@if ($excerpt !== null)
  <h2 class="board__section">{{ __('insights.board.assessor_summary') }}</h2>
  <p class="board__summary" dir="auto">{{ $excerpt['text'] }}@if ($excerpt['truncated']) <span class="board__pointer">{{ __('insights.board.summary_truncated') }}</span>@endif</p>
@endif

<div class="board__columns">
  @foreach (['strong_areas' => 'strong_areas', 'priority_gaps' => 'priority_gaps'] as $block => $label)
    <section>
      <h2 class="board__section">{{ __('insights.board.'.$label) }}</h2>
      @forelse ($data[$block] ?? [] as $item)
        <article class="board__item">
          <div class="board__item-head">
            <span><bdi class="latin">{{ $item['sub_code'] ?? '' }}</bdi> {{ $text($item['sub_name'] ?? null) }}</span>
            <bdi class="num" dir="ltr">{{ $item['percent'] ?? '' }}%</bdi>
          </div>
          @if (($item['quote'] ?? null) !== null)
            <p dir="auto">«{{ $item['quote'] }}»</p>
          @endif
        </article>
      @empty
        <p class="board__none">{{ __('insights.board.none_listed') }}</p>
      @endforelse
    </section>
  @endforeach
</div>

<h2 class="board__section">{{ __('insights.board.profile') }}</h2>
<div class="board__profile">
  <div>
    {{ __('insights.board.sub_criteria_by_class') }}
    <div class="board__counts">
      @foreach ($profile['classes'] ?? [] as $class => $count)
        <span class="board__count">{{ __('insights.classes.'.$class) }}: <bdi class="latin">{{ $count }}</bdi></span>
      @endforeach
    </div>
  </div>
  <div>
    {{ __('insights.board.main_criteria_by_band') }}
    <div class="board__counts">
      @foreach ($profile['bands'] ?? [] as $band => $count)
        <span class="board__count">{{ __('insights.bands.'.$band) }}: <bdi class="latin">{{ $count }}</bdi></span>
      @endforeach
    </div>
  </div>
</div>

<h2 class="board__section">{{ __('insights.board.criteria') }}</h2>
<table class="board__bars">
  <tbody>
    @foreach ($data['bars'] ?? [] as $bar)
      <tr>
        <td class="code">{{ $bar['code'] ?? '' }}</td>
        <td class="name">{{ $text($bar['name'] ?? null) }}</td>
        <td><div class="track"><div class="fill" style="inline-size: {{ $bar['percent'] ?? '0' }}%"></div></div></td>
        <td class="num"><bdi dir="ltr">{{ $bar['percent'] ?? '' }}%</bdi></td>
        <td class="band">{{ __('insights.bands.'.($bar['band'] ?? 'emerging')) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<footer class="board__footer">
  {{ __('insights.board.footer', ['number' => $result['number'] ?? '']) }}
  {{ __('insights.board.rules', ['gaps' => $data['rules']['gap_thresholds_version'] ?? '', 'bands' => $data['rules']['maturity_bands_version'] ?? '']) }}
  — {{ __('insights.board.snapshot', ['version' => $result['snapshot_version'] ?? '']) }}
  — {{ __('insights.board.generated_on') }}: @include('documents.partials.dates', ['gregorian' => $data['generated_on'] ?? '', 'hijri' => $data['generated_on_hijri'][$locale] ?? '', 'locale' => $locale]) <bdi class="latin">{{ $data['generated_at'] ?? '' }}</bdi>
  — <strong>{{ __('insights.board.confidential') }}</strong>
</footer>
@endsection
