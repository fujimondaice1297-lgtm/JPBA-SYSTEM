@extends('layouts.app')

@section('content')

{{-- この画面専用の軽量CSS（1行死守＋横スクロール） --}}
<style>
  /* 操作セルは折り返さない */
  .cell-actions { white-space: nowrap; }
  /* ボタン行：1行固定、足りなければ横スクロール */
  .btn-row {
    display: flex;
    flex-wrap: nowrap;         /* ← 折り返し禁止 */
    gap: .5rem;
    align-items: center;
    overflow-x: auto;          /* ← 横スクロール許可 */
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;     /* Firefox 細スクロール */
  }
  .btn-row .btn { flex: 0 0 auto; } /* ボタン幅を潰さない */

  /* 画面が狭い時は少しだけボタンを小さくする */
  @media (max-width: 768px) {
    .btn-row .btn {
      padding: .25rem .5rem;
      font-size: .8rem;
    }
  }
</style>

<h1>大会一覧</h1>

{{-- 検索フォーム --}}
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

{{-- 新規登録ボタン --}}
<a href="{{ route('tournaments.create') }}" class="btn btn-success mb-3">新規登録</a>

{{-- 一覧テーブル --}}
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
          {{ optional($tournament->entry_start)->format('Y-m-d') }}～
          {{ optional($tournament->entry_end)->format('Y-m-d') }}
        </td>
        <td>{{ $tournament->venue_name }}</td>

        {{-- ★ ここが1行固定の操作セル --}}
        <td class="cell-actions">
          <div class="btn-row">
            <a href="{{ route('tournaments.show', $tournament->id) }}"
               class="btn btn-info btn-sm flex-shrink-0">詳細</a>

            <a href="{{ route('tournaments.edit', $tournament->id) }}"
               class="btn btn-primary btn-sm flex-shrink-0">編集</a>

            <a href="{{ route('tournament_results.create', $tournament->id) }}"
               class="btn btn-info btn-sm flex-shrink-0">成績入力</a>

            <a href="{{ route('tournaments.prize_distributions.index', $tournament->id) }}"
               class="btn btn-warning btn-sm flex-shrink-0">賞金配分</a>

            <a href="{{ route('tournaments.point_distributions.index', $tournament->id) }}"
               class="btn btn-danger btn-sm flex-shrink-0">ポイント配分</a>

            @if(auth()->user()?->isAdmin() || auth()->user()?->isEditor())
              <form method="POST" action="{{ route('tournaments.participant_group.create', $tournament->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-dark btn-sm" title="この大会の参加者グループを作成">参加者グループ</button>
              </form>
            @endif

            {{-- ★ 管理者だけ：大会削除（/admin 側の destroy ルートへ） --}}
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
