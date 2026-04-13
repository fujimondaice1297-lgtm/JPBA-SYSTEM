@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">参加一覧</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">大会エントリーへ戻る</a>
      <a href="{{ route('member.tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-primary">抽選結果を見る</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">参加</div>
        <div class="fs-4 fw-bold">{{ $summary['entry_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">ウェイティング</div>
        <div class="fs-4 fw-bold">{{ $summary['waitlist_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">チェックイン済み</div>
        <div class="fs-4 fw-bold">{{ $summary['checked_in_count'] }}</div>
      </div></div>
    </div>
  </div>

  <form method="GET" action="{{ route('member.tournaments.entries.index', $tournament->id) }}" class="mb-3">
    <div class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label">検索</label>
        <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="ライセンスNo / 氏名 / フリガナ">
      </div>
      <div class="col-md-7 d-flex gap-2">
        <button type="submit" class="btn btn-primary">絞り込む</button>
        <a href="{{ route('member.tournaments.entries.index', $tournament->id) }}" class="btn btn-secondary">リセット</a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th>状態</th>
          <th>ライセンスNo</th>
          <th>氏名</th>
          <th>シフト</th>
          <th>レーン</th>
          <th>チェックイン</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $entry)
          @php $bowler = $entry->bowler; @endphp
          <tr>
            <td>
              @if ($entry->status === 'entry')
                <span class="badge bg-primary">参加</span>
              @elseif ($entry->status === 'waiting')
                <span class="badge bg-warning text-dark">ウェイティング</span>
              @else
                <span class="badge bg-secondary">{{ $entry->status_label }}</span>
              @endif
            </td>
            <td>{{ $bowler->license_no ?? '-' }}</td>
            <td>{{ $bowler->name_kanji ?? '-' }}</td>
            <td>{{ $entry->shift ?? '-' }}</td>
            <td>{{ $entry->lane ?? '-' }}</td>
            <td>{{ optional($entry->checked_in_at)->format('Y-m-d H:i') ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted">該当データはありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $entries->links() }}
</div>
@endsection