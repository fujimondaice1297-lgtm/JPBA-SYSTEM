@extends('public.layout')

@section('title', $definition->name . '｜' . $tournament->name . '｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '速報・成績')

@push('styles')
<style>
  .jpba-aggregate-table-wrap { overflow-x:auto; }
  .jpba-aggregate-table { width:100%; min-width:860px; border-collapse:collapse; }
  .jpba-aggregate-table th,
  .jpba-aggregate-table td { border:1px solid var(--jpba-line); padding:8px 9px; text-align:right; white-space:nowrap; }
  .jpba-aggregate-table th { background:var(--jpba-soft); color:#35465a; }
  .jpba-aggregate-table .subject { text-align:left; min-width:230px; white-space:normal; }
  .jpba-aggregate-members { margin-top:3px; color:#667085; font-size:.86rem; }
  .jpba-result-state { display:inline-block; padding:2px 7px; border-radius:12px; font-size:.8rem; }
  .jpba-result-state.complete { background:#e8f4ed; color:#17643a; }
  .jpba-result-state.incomplete { background:#fff2d8; color:#805500; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
  <div>
    <h1 class="jpba-page-title mb-1">{{ $definition->name }}</h1>
    <div class="fw-bold">{{ $tournament->name }}</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    @if($mode === 'official')
      <a class="jpba-small-button" href="{{ route('public.tournaments.aggregate', [$tournament, $definition, 'mode' => 'live']) }}">速報へ</a>
    @elseif($hasOfficialSnapshot)
      <a class="jpba-small-button" href="{{ route('public.tournaments.aggregate', [$tournament, $definition, 'mode' => 'official']) }}">正式成績へ</a>
    @endif
    <a class="jpba-small-button" href="{{ route('public.tournaments.aggregate.pdf', [$tournament, $definition, 'mode' => $mode]) }}">PDF</a>
    <a class="jpba-small-button" href="{{ route('public.tournaments.show', $tournament) }}">大会ページへ戻る</a>
  </div>
</div>

<div class="alert {{ $mode === 'official' ? 'alert-primary' : 'alert-warning' }} py-2">
  @if($mode === 'official')
    公開済みの正式成績です。
  @else
    大会進行中の速報値です。訂正・再集計により順位やスコアが変わる場合があります。
  @endif
  <span class="ms-2 small">更新: {{ optional($updatedAt)->format('Y/m/d H:i') }}</span>
</div>

<section class="jpba-panel" aria-labelledby="aggregate-result-heading">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 id="aggregate-result-heading" class="jpba-section-title mb-0">{{ $definition->name }}</h2>
    <div class="text-muted">{{ number_format($rows->total()) }}件</div>
  </div>

  <div class="jpba-aggregate-table-wrap">
    <table class="jpba-aggregate-table">
      <thead>
        <tr>
          <th>順位</th>
          <th class="subject">{{ $definition->subject_type === 'group' ? 'チーム／ダブルス' : '選手' }}</th>
          @foreach($sources as $source)
            <th>{{ $source['label'] }}</th>
          @endforeach
          <th>ゲーム数</th>
          <th>トータルピン</th>
          <th>アベレージ</th>
          <th>状態</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          @php
            $license = $row['pro_bowler_id']
                ? \App\Support\PublicLicenseNumber::format($row['pro_bowler_license_no'] ?? '')
                : '';
            $members = $groupMembers[(int)($row['competitor_group_id'] ?? 0)] ?? [];
          @endphp
          <tr>
            <td>{{ $row['is_complete'] ? ($row['ranking'] ?: '-') : '-' }}</td>
            <td class="subject">
              <strong>{{ $row['display_name'] }}</strong>
              @if($license !== '')<span class="text-muted">（{{ $license }}）</span>@endif
              @if($members)
                <div class="jpba-aggregate-members">{{ implode('／', $members) }}</div>
              @endif
              @foreach((array)($row['incomplete_reasons'] ?? []) as $reason)
                <div class="small text-danger">{{ $reason }}</div>
              @endforeach
            </td>
            @foreach($sources as $source)
              @php $part = data_get($row, 'source_breakdown.' . $source['id']); @endphp
              <td>
                {{ $part ? number_format((int)($part['total_pin'] ?? 0)) : '-' }}
                @if($part)<div class="small text-muted">{{ (int)($part['games'] ?? 0) }}G</div>@endif
              </td>
            @endforeach
            <td>{{ number_format((int)$row['games']) }}G</td>
            <td><strong>{{ number_format((int)$row['total_pin']) }}</strong></td>
            <td>{{ $row['average'] !== null ? number_format((float)$row['average'], 2) : '-' }}</td>
            <td>
              <span class="jpba-result-state {{ $row['is_complete'] ? 'complete' : 'incomplete' }}">
                {{ $row['is_complete'] ? '集計完了' : '未完了' }}
              </span>
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ count($sources) + 6 }}" class="text-center text-muted py-4">対象スコアはありません。</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $rows->links() }}</div>
</section>

<p class="small text-muted">
  この競技別集計はチーム／ダブルス／オールエベンツ順位の表示専用です。個人の公式ポイント・賞金・タイトルへ重複計上しません。
</p>
@endsection
