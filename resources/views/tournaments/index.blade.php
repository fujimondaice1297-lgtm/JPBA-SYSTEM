@extends('layouts.app')

@section('content')

<style>
  .cell-actions { white-space: nowrap; }
  .btn-row {
    display: flex;
    flex-wrap: nowrap;
    gap: .5rem;
    align-items: center;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }
  .btn-row .btn { flex: 0 0 auto; }

  @media (max-width: 768px) {
    .btn-row .btn {
      padding: .25rem .5rem;
      font-size: .8rem;
    }
  }
</style>

<h1>大会一覧</h1>

<form method="GET" action="{{ route('tournaments.index') }}" class="mb-4">
  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <input type="text" name="name" value="{{ request('name') }}" placeholder="大会名" class="form-control" style="width: 200px;">
    <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" style="width: 180px;">
    <input type="text" name="venue_name" value="{{ request('venue_name') }}" placeholder="会場名" class="form-control" style="width: 200px;">
    <button type="submit" class="btn btn-primary">検索</button>
    <a href="{{ route('tournaments.index') }}" class="btn btn-warning">リセット</a>
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-primary btn-sm">大会使用ボール登録へ</a>
    <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary btn-sm">使用ボール一覧（管理）</a>
    <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
  </div>
</form>

<a href="{{ route('tournaments.create') }}" class="btn btn-success mb-3">新規登録</a>

<table class="table table-bordered align-middle">
  <thead>
    <tr>
      <th>ID</th>
      <th>大会名</th>
      <th>開催期間</th>
      <th>申込期間</th>
      <th>会場名</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    @forelse($tournaments as $tournament)
      <tr>
        <td>{{ $tournament->id }}</td>
        <td>{{ $tournament->name }}</td>
        <td>
          {{ optional($tournament->start_date)->format('Y-m-d') }}～
          {{ optional($tournament->end_date)->format('Y-m-d') }}
        </td>
        <td>
          {{ optional($tournament->entry_start)->format('Y-m-d H:i') }}～
          {{ optional($tournament->entry_end)->format('Y-m-d H:i') }}
        </td>
        <td>{{ $tournament->venue_name }}</td>

        <td class="cell-actions">
          <div class="btn-row">
            <a href="{{ route('tournaments.show', $tournament->id) }}"
               class="btn btn-info btn-sm flex-shrink-0">詳細</a>

            <a href="{{ route('tournaments.edit', $tournament->id) }}"
               class="btn btn-primary btn-sm flex-shrink-0">編集</a>

            @if(auth()->user()?->isAdmin())
              <a href="{{ route('admin.tournaments.draw.settings', $tournament->id) }}"
                 class="btn btn-outline-primary btn-sm flex-shrink-0">運営設定</a>
            @endif

            <a href="{{ route('tournament_results.create', $tournament->id) }}"
               class="btn btn-info btn-sm flex-shrink-0">成績入力</a>

            <a href="{{ route('tournaments.prize_distributions.index', $tournament->id) }}"
               class="btn btn-warning btn-sm flex-shrink-0">賞金配分</a>

            <a href="{{ route('tournaments.point_distributions.index', $tournament->id) }}"
               class="btn btn-danger btn-sm flex-shrink-0">ポイント配分</a>

            <a href="{{ route('tournaments.entries.index', $tournament->id) }}"
               class="btn btn-outline-dark btn-sm flex-shrink-0">エントリー一覧</a>

            <a href="{{ route('tournaments.draws.index', $tournament->id) }}"
               class="btn btn-outline-dark btn-sm flex-shrink-0">抽選一覧</a>

            <a href="{{ route('tournaments.draws.index', ['tournament' => $tournament->id, 'pending_draw' => 1]) }}"
               class="btn btn-outline-secondary btn-sm flex-shrink-0">未抽選</a>

            @if(auth()->user()?->isAdmin() || auth()->user()?->isEditor())
              <form method="POST" action="{{ route('tournaments.participant_group.create', $tournament->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-dark btn-sm" title="この大会の参加者グループを作成">参加者グループ</button>
              </form>
            @endif

            @if(auth()->user()?->isAdmin())
              <form method="POST"
                    action="{{ route('admin.tournaments.destroy', $tournament->id) }}"
                    class="d-inline"
                    onsubmit="return confirm('大会「{{ $tournament->name }}」を削除します。元に戻せません。よろしいですか？');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="btn btn-outline-danger btn-sm"
                        title="大会を完全に削除（管理者専用）">
                  削除
                </button>
              </form>
            @endif
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="6">該当する大会はありません。</td></tr>
    @endforelse
  </tbody>
</table>
@endsection