@extends('public.layout')

@section('title', $event->year.'年度プロテスト｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'プロテスト 速報・結果')

@push('styles')
<style>
  .protest-result-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
  .protest-result-card { padding: 15px; border: 1px solid var(--jpba-line); border-radius: 6px; background: #fff; }
  .protest-result-card h3 { margin: 0 0 10px; color: var(--jpba-blue); font-size: 1rem; }
  .protest-result-card a { display: block; padding: 8px 0; border-top: 1px dotted var(--jpba-line); }
  .protest-privacy { padding: 12px 14px; border-left: 4px solid var(--jpba-red); background: #fff7f7; font-size: .9rem; }
  @media (max-width: 760px) { .protest-result-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">{{ $event->year }}年度 プロテスト</h1>

<section class="jpba-panel">
  <h2 class="jpba-section-title">{{ $event->name }}</h2>
  @if($event->public_summary)<p>{!! nl2br(e($event->public_summary)) !!}</p>@endif
  <p class="mb-0">実施期間：{{ optional($event->start_date)->format('Y/n/j') ?: '-' }}@if($event->end_date && (!$event->start_date || !$event->end_date->equalTo($event->start_date))) 〜 {{ $event->end_date->format('Y/n/j') }}@endif</p>
</section>

<p class="protest-privacy">受験者のプライバシー保護のため、途中結果では年齢・生年月日・住所・連絡先を公開していません。</p>

<section class="jpba-panel">
  <h2 class="jpba-section-title">速報・結果</h2>
  <div class="protest-result-grid">
    @forelse($event->sessions->groupBy(fn ($session) => $session->gender.'-'.$session->stage_code) as $sessions)
      @php($first = $sessions->first())
      <article class="protest-result-card">
        <h3>{{ $first->gender_label }} {{ $first->stage_label }}</h3>
        @foreach($sessions as $session)
          <a href="{{ route('public.pro_tests.sessions.show', [$event, $session]) }}">
            {{ $session->day_number }}日目（{{ $session->game_end }}G終了時点）
            <small>第{{ $session->latestPublication->revision }}版・{{ $session->latestPublication->published_at->format('Y/n/j H:i') }}更新</small>
          </a>
        @endforeach
      </article>
    @empty
      <p class="mb-0 text-muted">公開中の速報はありません。</p>
    @endforelse
  </div>
</section>

@if($event->final_results_published_at)
<section class="jpba-panel">
  <h2 class="jpba-section-title">最終合格者</h2>
  @if($finalPublication)<p>第{{ $finalPublication->revision }}版 / {{ $finalPublication->published_at->format('Y/n/j H:i') }}更新</p>@endif
  <div class="table-responsive">
    <table class="jpba-data-table">
      <thead><tr><th>区分</th><th>受験番号</th><th>ライセンスNo.</th><th>氏名</th><th>フリガナ</th></tr></thead>
      <tbody>
      @foreach($passedCandidates as $candidate)
        <tr>
          <td>{{ $candidate->gender_label }}@if($candidate->gender === 'M' && $event->male_generation) {{ $event->male_generation }}@elseif($candidate->gender === 'F' && $event->female_generation) {{ $event->female_generation }}@endif</td>
          <td>{{ $candidate->exam_number }}</td>
          <td>{{ $candidate->license_no ?: '-' }}</td>
          <td>@if($candidate->proBowler)<a href="{{ route('public.players.show', $candidate->proBowler) }}">{{ $candidate->name }}</a>@else{{ $candidate->name }}@endif</td>
          <td>{{ $candidate->name_kana ?: '-' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

<a class="jpba-outline-button" href="{{ route('public.protest') }}">プロテスト案内へ戻る</a>
@endsection
