@extends('public.layout')

@section('title', $tournament->name . '｜トーナメント｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'トーナメント')

@push('styles')
<style>
  .jpba-tournament-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 18px;
    align-items: start;
  }

  .jpba-tournament-image {
    width: 100%;
    max-height: 220px;
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    object-fit: cover;
    background: var(--jpba-soft);
  }

  .jpba-badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 8px 0 12px;
  }

  .jpba-badge {
    display: inline-flex;
    min-height: 24px;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    background: #edf3fb;
    color: var(--jpba-blue);
    font-size: .78rem;
    font-weight: 700;
  }

  .jpba-link-list {
    display: grid;
    gap: 8px;
  }

  .jpba-link-list a {
    display: block;
    padding: 8px 10px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: var(--jpba-soft);
    text-decoration: none;
    font-weight: 700;
    overflow-wrap: anywhere;
  }

  .jpba-result-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--jpba-line);
  }

  .jpba-result-table th,
  .jpba-result-table td {
    border: 1px solid var(--jpba-line);
    padding: 8px 9px;
    vertical-align: top;
  }

  .jpba-result-table th {
    background: var(--jpba-soft);
    color: #35465a;
    font-weight: 700;
    white-space: nowrap;
  }

  .jpba-result-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .jpba-result-card {
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    padding: 10px;
    background: #fff;
  }

  .jpba-result-card-title {
    margin: 0 0 4px;
    color: var(--jpba-blue);
    font-weight: 700;
  }

  @media (max-width: 820px) {
    .jpba-tournament-hero { grid-template-columns: 1fr; }
    .jpba-result-cards { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Carbon;
  $week = ['日','月','火','水','木','金','土'];
  $fmt = function ($date) use ($week) {
      if (!$date) {
          return null;
      }

      $carbon = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);

      return $carbon->format('Y年n月j日') . '（' . $week[$carbon->dayOfWeek] . '）';
  };

  $period = $fmt($tournament->start_date) ?: '日程未定';
  if ($tournament->end_date && !$tournament->start_date?->isSameDay($tournament->end_date)) {
      $period .= ' - ' . $fmt($tournament->end_date);
  }

  $heroImage = $tournament->hero_image_path ?: ($tournament->image_path ?: null);
  $organizationLabels = [
      'host' => '主催',
      'special_sponsor' => '特別協賛',
      'sponsor' => '協賛',
      'support' => '後援',
      'cooperation' => '協力',
  ];
  $organizations = collect($organizationLabels)->mapWithKeys(function ($label, $key) use ($tournament) {
      $items = $tournament->organizations
          ->where('category', $key)
          ->sortBy('sort_order')
          ->values();

      return [$key => ['label' => $label, 'items' => $items, 'fallback' => $tournament->{$key} ?? null]];
  });
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="jpba-page-title mb-0">{{ $tournament->name }}</h1>
  <a class="jpba-small-button" href="{{ route('public.tournaments.index') }}">大会一覧へ戻る</a>
</div>

<section class="jpba-panel">
  <div class="jpba-tournament-hero">
    <div>
      <div class="jpba-badge-row">
        <span class="jpba-badge">{{ $tournament->official_type_label }}</span>
        <span class="jpba-badge">{{ $tournament->gender_label }}</span>
        @if($tournament->title_category)
          <span class="jpba-badge">{{ $tournament->title_category }}</span>
        @endif
      </div>

      <table class="jpba-data-table">
        <tbody>
          <tr><th>開催日</th><td>{{ $period }}</td></tr>
          <tr><th>会場</th><td>{{ $tournament->venue_name ?: '-' }}</td></tr>
          <tr><th>所在地</th><td>{{ $tournament->venue_address ?: '-' }}</td></tr>
          <tr><th>観戦</th><td>{{ $tournament->spectator_policy ?: '-' }}</td></tr>
          <tr><th>入場料</th><td>{!! nl2br(e($tournament->admission_fee ?: '-')) !!}</td></tr>
          <tr><th>配信</th><td>
            {{ $tournament->streaming ?: '-' }}
            @if($tournament->streaming_url)
              <a href="{{ $tournament->streaming_url }}" target="_blank" rel="noopener">配信ページ</a>
            @endif
          </td></tr>
          <tr><th>TV放映</th><td>
            {{ $tournament->broadcast ?: '-' }}
            @if($tournament->broadcast_url)
              <a href="{{ $tournament->broadcast_url }}" target="_blank" rel="noopener">放映サイト</a>
            @endif
          </td></tr>
        </tbody>
      </table>
    </div>

    <div>
      @if($heroImage)
        <img class="jpba-tournament-image" src="{{ asset('storage/' . ltrim($heroImage, '/')) }}" alt="{{ $tournament->name }}">
      @else
        <div class="jpba-tournament-image d-flex align-items-center justify-content-center text-muted fw-bold">JPBA</div>
      @endif
    </div>
  </div>
</section>

<section class="jpba-panel" aria-labelledby="file-heading">
  <h2 id="file-heading" class="jpba-section-title">資料・速報・成績</h2>

  @if(!empty($fileLinks) || !empty($scheduleLinks))
    <div class="jpba-link-list">
      @foreach($fileLinks as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
      @endforeach
      @foreach($scheduleLinks as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener">
          @if($link['date']){{ $link['date'] }} / @endif{{ $link['label'] }}
        </a>
      @endforeach
    </div>
  @else
    <p class="mb-0 text-muted">公開資料・速報リンクはまだ登録されていません。</p>
  @endif
</section>

@if(!empty($resultCards))
  <section class="jpba-panel" aria-labelledby="highlight-heading">
    <h2 id="highlight-heading" class="jpba-section-title">トピックス・ハイライト</h2>

    <div class="jpba-result-cards">
      @foreach($resultCards as $card)
        <article class="jpba-result-card">
          <h3 class="jpba-result-card-title">
            @if($card['url'] !== '#')
              <a href="{{ $card['url'] }}" target="_blank" rel="noopener">{{ $card['title'] ?: '関連情報' }}</a>
            @else
              {{ $card['title'] ?: '関連情報' }}
            @endif
          </h3>
          @if($card['player'])
            <div class="fw-bold">{{ $card['player'] }}</div>
          @endif
          @if($card['balls'])
            <div class="small mt-1">{!! nl2br(e($card['balls'])) !!}</div>
          @endif
          @if($card['note'])
            <div class="text-muted small mt-1">{{ $card['note'] }}</div>
          @endif
        </article>
      @endforeach
    </div>
  </section>
@endif

<section class="jpba-panel" aria-labelledby="result-heading">
  <h2 id="result-heading" class="jpba-section-title">成績</h2>

  @if($resultRows->count())
    <table class="jpba-result-table">
      <thead>
        <tr>
          <th>順位</th>
          <th>選手</th>
          <th>ライセンスNo.</th>
          <th>トータル</th>
          <th>ゲーム</th>
          <th>アベレージ</th>
          <th>ポイント</th>
        </tr>
      </thead>
      <tbody>
        @foreach($resultRows as $row)
          <tr>
            <td>{{ $row->ranking ?: '-' }}</td>
            <td>{{ $row->amateur_name ?: ($row->pro_name ?: '-') }}</td>
            <td>{{ $row->pro_bowler_license_no ?: '-' }}</td>
            <td>{{ $row->total_pin !== null ? number_format((int)$row->total_pin) : '-' }}</td>
            <td>{{ $row->games ?: '-' }}</td>
            <td>{{ $row->average !== null ? number_format((float)$row->average, 2) : '-' }}</td>
            <td>{{ $row->points !== null ? number_format((float)$row->points, 2) : '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="mb-0 text-muted">公開できる成績はまだ登録されていません。</p>
  @endif
</section>

<section class="jpba-panel" aria-labelledby="organization-heading">
  <h2 id="organization-heading" class="jpba-section-title">大会情報</h2>

  <table class="jpba-data-table">
    <tbody>
      @foreach($organizations as $group)
        @if($group['items']->count() || $group['fallback'])
          <tr>
            <th>{{ $group['label'] }}</th>
            <td>
              @if($group['items']->count())
                @foreach($group['items'] as $organization)
                  <div>
                    @if($organization->url)
                      <a href="{{ $organization->url }}" target="_blank" rel="noopener">{{ $organization->name }}</a>
                    @else
                      {{ $organization->name }}
                    @endif
                  </div>
                @endforeach
              @else
                {{ $group['fallback'] }}
              @endif
            </td>
          </tr>
        @endif
      @endforeach
      <tr><th>賞金</th><td>{!! nl2br(e($tournament->prize ?: '-')) !!}</td></tr>
      <tr><th>出場条件</th><td>{!! nl2br(e($tournament->entry_conditions ?: '-')) !!}</td></tr>
      <tr><th>資料メモ</th><td>{!! nl2br(e($tournament->materials ?: '-')) !!}</td></tr>
      @if($tournament->previous_event)
        <tr>
          <th>前年大会</th>
          <td>
            @if($tournament->previous_event_url)
              <a href="{{ $tournament->previous_event_url }}" target="_blank" rel="noopener">{{ $tournament->previous_event }}</a>
            @else
              {{ $tournament->previous_event }}
            @endif
          </td>
        </tr>
      @endif
    </tbody>
  </table>
</section>
@endsection
