@extends('public.layout')

@section('title', $session->display_name.'｜'.$event->year.'年度プロテスト')
@section('breadcrumb', 'プロテスト 速報・結果')

@push('styles')
<style>
  .protest-live-note { padding: 12px 14px; border-left: 4px solid var(--jpba-red); background: #fff7f7; }
  .protest-live-table { min-width: 900px; }
  .protest-live-table .numeric { text-align: right; font-variant-numeric: tabular-nums; }
  .protest-live-table .total { font-weight: 800; }
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">プロテスト速報・結果</h1>

<section class="jpba-panel">
  <h2 class="jpba-section-title">{{ $event->name }}</h2>
  <p class="mb-1"><strong>{{ $session->display_name }}　{{ $session->game_end }}G終了時点</strong></p>
  <p class="mb-0">第{{ $publication->revision }}版 / 最終更新 {{ $publication->published_at->format('Y/n/j H:i') }}</p>
</section>

<p class="protest-live-note">掲載内容は確認済み時点の速報です。訂正・再集計により順位やスコアが変わる場合があります。受験者の年齢・生年月日・住所・連絡先は公開していません。</p>

<section class="jpba-panel">
  <div class="table-responsive">
    <table class="jpba-data-table protest-live-table">
      <thead>
        <tr>
          <th>順位</th><th>受験番号</th><th>氏名</th><th>フリガナ</th><th>居住地</th><th>投球</th>
          @foreach($gameNumbers as $game)<th class="numeric">{{ $game }}G</th>@endforeach
          <th class="numeric">ゲーム数</th><th class="numeric">トータル</th><th class="numeric">AVG</th><th>結果</th>
        </tr>
      </thead>
      <tbody>
      @foreach($publication->rows as $row)
        <tr>
          <td class="numeric">{{ $row->rank }}</td><td>{{ $row->exam_number }}</td><td>{{ $row->name }}</td><td>{{ $row->name_kana ?: '-' }}</td><td>{{ $row->resident_prefecture ?: '-' }}</td><td>{{ $row->handedness ?: '-' }}</td>
          @foreach($gameNumbers as $game)<td class="numeric">{{ $row->session_scores[(string) $game] ?? '-' }}</td>@endforeach
          <td class="numeric">{{ $row->games }}</td><td class="numeric total">{{ number_format($row->total_pin) }}</td><td class="numeric">{{ number_format((float) $row->average, 2) }}</td><td>{{ $row->result_label ?: '-' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</section>

<a class="jpba-outline-button" href="{{ route('public.pro_tests.show', $event) }}">{{ $event->year }}年度の一覧へ戻る</a>
@endsection
