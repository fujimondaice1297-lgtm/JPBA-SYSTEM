@extends('public.layout')

@section('title', 'トーナメント｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'トーナメント')

@push('styles')
<style>
  .jpba-tournament-form {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 12px;
  }

  .jpba-tournament-form label {
    display: block;
    margin-bottom: 4px;
    font-weight: 700;
  }

  .jpba-tournament-form select {
    width: 100%;
    min-height: 36px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    padding: 6px 8px;
  }

  .jpba-tournament-form .span-2 { grid-column: span 2; }
  .jpba-tournament-form .span-3 { grid-column: span 3; }
  .jpba-tournament-form .span-4 { grid-column: span 4; }
  .jpba-tournament-form .span-12 { grid-column: span 12; }

  .jpba-action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .jpba-search-button {
    min-height: 36px;
    border: 1px solid var(--jpba-blue);
    border-radius: 4px;
    background: var(--jpba-blue);
    color: #fff;
    padding: 6px 16px;
    font-weight: 700;
  }

  .jpba-outline-button {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: #fff;
    padding: 6px 12px;
    text-decoration: none;
    font-weight: 700;
  }

  .jpba-tournament-list {
    display: grid;
    gap: 12px;
  }

  .jpba-tournament-row {
    display: grid;
    grid-template-columns: 132px minmax(0, 1fr);
    gap: 14px;
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    background: #fff;
    padding: 12px;
  }

  .jpba-tournament-date {
    color: var(--jpba-blue);
    font-weight: 700;
  }

  .jpba-tournament-type {
    display: inline-flex;
    min-height: 24px;
    align-items: center;
    margin-top: 6px;
    padding: 2px 8px;
    border-radius: 4px;
    background: #edf3fb;
    color: var(--jpba-blue);
    font-size: .78rem;
    font-weight: 700;
  }

  .jpba-tournament-title {
    margin: 0 0 4px;
    font-size: 1.05rem;
    font-weight: 700;
  }

  .jpba-tournament-meta {
    color: #596675;
    font-size: .88rem;
  }

  .jpba-tournament-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
  }

  .jpba-tournament-links a {
    display: inline-flex;
    min-height: 26px;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    background: #f1f5f9;
    text-decoration: none;
    font-size: .78rem;
    font-weight: 700;
  }

  .jpba-pagination { margin-top: 14px; }

  @media (max-width: 760px) {
    .jpba-tournament-form { grid-template-columns: 1fr; }
    .jpba-tournament-form .span-2,
    .jpba-tournament-form .span-3,
    .jpba-tournament-form .span-4,
    .jpba-tournament-form .span-12 { grid-column: auto; }
    .jpba-tournament-row { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Carbon;
  $week = ['日','月','火','水','木','金','土'];
  $dateLabel = function ($tournament) use ($week) {
      if (!$tournament->start_date) {
          return '日程未定';
      }

      $start = $tournament->start_date instanceof \DateTimeInterface
          ? Carbon::instance($tournament->start_date)
          : Carbon::parse($tournament->start_date);
      $end = $tournament->end_date instanceof \DateTimeInterface
          ? Carbon::instance($tournament->end_date)
          : ($tournament->end_date ? Carbon::parse($tournament->end_date) : null);

      $label = $start->format('m/d') . '（' . $week[$start->dayOfWeek] . '）';
      if ($end && !$start->isSameDay($end)) {
          $label .= ' - ' . $end->format('m/d') . '（' . $week[$end->dayOfWeek] . '）';
      }

      return $label;
  };
@endphp

<h1 class="jpba-page-title">トーナメント</h1>

<section class="jpba-panel" aria-labelledby="tournament-search-heading">
  <h2 id="tournament-search-heading" class="jpba-section-title">条件検索</h2>

  <form method="GET" action="{{ route('public.tournaments.index') }}" class="jpba-tournament-form">
    <div class="span-3">
      <label for="type">大会区分</label>
      <select id="type" name="type">
        <option value="">すべて</option>
        @foreach($typeOptions as $value => $label)
          <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="span-2">
      <label for="year">年</label>
      <select id="year" name="year">
        <option value="">すべて</option>
        @foreach($years as $year)
          <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string)$year)>{{ $year }}</option>
        @endforeach
      </select>
    </div>

    <div class="span-2">
      <label for="month">月</label>
      <select id="month" name="month">
        <option value="">すべて</option>
        @for($month = 1; $month <= 12; $month++)
          <option value="{{ $month }}" @selected((string)($filters['month'] ?? '') === (string)$month)>{{ $month }}月</option>
        @endfor
      </select>
    </div>

    <div class="span-3">
      <label for="region">地区</label>
      <select id="region" name="region">
        <option value="">すべて</option>
        @foreach($regionOptions as $value => $option)
          <option value="{{ $value }}" @selected(($filters['region'] ?? '') === $value)>{{ $option['label'] }}</option>
        @endforeach
      </select>
    </div>

    <div class="span-12 jpba-action-row">
      <button type="submit" class="jpba-search-button">検索する</button>
      <a class="jpba-outline-button" href="{{ route('public.tournaments.index') }}">リセット</a>
    </div>
  </form>
</section>

<section class="jpba-panel" aria-labelledby="tournament-result-heading">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 id="tournament-result-heading" class="jpba-section-title mb-0">大会一覧</h2>
    <div class="text-muted">該当件数: {{ number_format($tournaments->total()) }}件</div>
  </div>

  @if($tournaments->count())
    <div class="jpba-tournament-list">
      @foreach($tournaments as $tournament)
        <article class="jpba-tournament-row">
          <div>
            <div class="jpba-tournament-date">{{ $dateLabel($tournament) }}</div>
            <div>{{ $tournament->year ?: optional($tournament->start_date)->year }}</div>
            <span class="jpba-tournament-type">{{ $tournament->official_type_label }}</span>
          </div>

          <div>
            <h3 class="jpba-tournament-title">
              <a href="{{ route('public.tournaments.show', $tournament) }}">{{ $tournament->name }}</a>
            </h3>
            <div class="jpba-tournament-meta">
              {{ $tournament->venue_name ?: '会場未定' }}
              @if($tournament->venue_address)
                / {{ $tournament->venue_address }}
              @endif
            </div>

            <div class="jpba-tournament-links">
              <a href="{{ route('public.tournaments.show', $tournament) }}">大会ページ</a>
              @foreach($tournament->files->take(3) as $file)
                <a href="{{ asset('storage/' . ltrim($file->file_path, '/')) }}" target="_blank" rel="noopener">
                  {{ $file->title ?: '資料' }}
                </a>
              @endforeach
            </div>
          </div>
        </article>
      @endforeach
    </div>

    <div class="jpba-pagination">
      {{ $tournaments->links() }}
    </div>
  @else
    <p class="mb-0 text-muted">条件に該当する大会はありません。</p>
  @endif
</section>
@endsection
