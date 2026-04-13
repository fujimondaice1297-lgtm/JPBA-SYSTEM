@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">抽選結果一覧（管理）</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ戻る</a>
      <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-dark">エントリー一覧</a>
      <a href="{{ route('member.tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-primary">参加選手向け抽選結果</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">参加</div>
        <div class="fs-4 fw-bold">{{ $summary['entry_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">シフト未抽選</div>
        <div class="fs-4 fw-bold">{{ $summary['pending_shift_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">レーン未抽選</div>
        <div class="fs-4 fw-bold">{{ $summary['pending_lane_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-muted small">チェックイン済み</div>
        <div class="fs-4 fw-bold">{{ $summary['checked_in_count'] }}</div>
      </div></div>
    </div>
  </div>

  <form method="GET" action="{{ route('tournaments.draws.index', $tournament->id) }}" class="mb-3">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">検索</label>
        <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="ライセンスNo / 氏名 / フリガナ">
      </div>
      <div class="col-md-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="pending_draw" value="1" id="pending_draw" {{ $pendingDraw ? 'checked' : '' }}>
          <label class="form-check-label" for="pending_draw">
            未抽選のみ表示
          </label>
        </div>
      </div>
      <div class="col-md-5 d-flex gap-2">
        <button type="submit" class="btn btn-primary">絞り込む</button>
        <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-secondary">リセット</a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th>ライセンスNo</th>
          <th>氏名</th>
          <th>シフト</th>
          <th>レーン</th>
          <th>ボール数</th>
          <th>チェックイン</th>
          <th>状態</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $entry)
          @php $bowler = $entry->bowler; @endphp
          <tr>
            <td>{{ $bowler->license_no ?? '-' }}</td>
            <td>{{ $bowler->name_kanji ?? '-' }}</td>
            <td>
              @if ($entry->shift)
                <span class="badge bg-info text-dark">{{ $entry->shift }}</span>
              @else
                <span class="badge bg-warning text-dark">未抽選</span>
              @endif
            </td>
            <td>
              @if ($entry->lane)
                <span class="badge bg-secondary">{{ $entry->lane }}</span>
              @else
                <span class="badge bg-warning text-dark">未抽選</span>
              @endif
            </td>
            <td>{{ $entry->balls_count }}</td>
            <td>{{ optional($entry->checked_in_at)->format('Y-m-d H:i') ?? '-' }}</td>
            <td>
              @if (!$entry->shift || !$entry->lane)
                <span class="badge bg-warning text-dark">抽選未完了</span>
              @elseif (!$entry->checked_in_at)
                <span class="badge bg-light text-dark">抽選済 / 未チェックイン</span>
              @else
                <span class="badge bg-success">抽選済 / チェックイン済</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted">該当データはありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $entries->links() }}
</div>
@endsection