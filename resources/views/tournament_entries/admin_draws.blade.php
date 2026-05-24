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
      <a href="{{ route('tournaments.seed_players.index', $tournament->id) }}" class="btn btn-outline-success">優先出場者一覧</a>
      <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-dark">エントリー一覧</a>
      <a href="{{ route('member.tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-primary">参加選手向け抽選結果</a>
      <a href="{{ route('tournaments.lane_movement_table.show', $tournament->id) }}" class="btn btn-outline-warning">レーン移動表</a>
      <a href="{{ route('tournaments.operation_logs.index', $tournament->id) }}" class="btn btn-outline-info">運用ログ</a>
      <a href="{{ route('tournaments.draw_reminders.create', ['tournament' => $tournament->id, 'pending_type' => 'either']) }}"
         class="btn btn-outline-danger">未抽選DM</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="alert alert-info small">
    抽選一覧では、参加権利がある参加者を最優先にし、その中で優先出場順位を見て表示します。
    抽選ロジック自体は変更せず、運用者が未抽選者・優先出場者・参加権利を確認しやすい表示にしています。
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">参加</div>
        <div class="fs-4 fw-bold">{{ $summary['entry_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card border-success"><div class="card-body">
        <div class="text-muted small">優先出場 参加</div>
        <div class="fs-4 fw-bold text-success">{{ $summary['priority_entry_count'] ?? 0 }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card border-danger"><div class="card-body">
        <div class="text-muted small">優先出場 未登録</div>
        <div class="fs-4 fw-bold text-danger">{{ $summary['priority_missing_count'] ?? 0 }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">希望シフトあり</div>
        <div class="fs-4 fw-bold">{{ $summary['preferred_shift_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">シフト未抽選</div>
        <div class="fs-4 fw-bold">{{ $summary['pending_shift_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">レーン未抽選</div>
        <div class="fs-4 fw-bold">{{ $summary['pending_lane_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">チェックイン済み</div>
        <div class="fs-4 fw-bold">{{ $summary['checked_in_count'] }}</div>
      </div></div>
    </div>
  </div>

  @if (($summary['priority_missing_count'] ?? 0) > 0)
    <div class="alert alert-warning small d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        優先出場者一覧に登録済みで、まだエントリー / ウェイティングに存在しない選手が
        <strong>{{ $summary['priority_missing_count'] }}</strong> 名います。
        抽選前にエントリー一覧からウェイティング登録してください。
      </div>
      <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-sm btn-warning">
        エントリー一覧で登録
      </a>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header fw-bold">事務局一括抽選</div>
    <div class="card-body">
      <form method="POST" action="{{ route('tournaments.draws.bulk', $tournament->id) }}" class="d-flex flex-wrap gap-2 align-items-center">
        @csrf

        <button type="submit"
                name="target"
                value="all"
                class="btn btn-danger"
                onclick="return confirm('未抽選者を一括抽選します。よろしいですか？');">
          未抽選者を一括抽選
        </button>

        @if ($tournament->use_shift_draw)
          <button type="submit"
                  name="target"
                  value="shift"
                  class="btn btn-outline-success"
                  onclick="return confirm('シフト未抽選者のみ一括抽選します。よろしいですか？');">
            シフト未抽選だけ一括抽選
          </button>
        @endif

        @if ($tournament->use_lane_draw)
          <button type="submit"
                  name="target"
                  value="lane"
                  class="btn btn-outline-secondary"
                  onclick="return confirm('レーン未抽選者のみ一括抽選します。よろしいですか？');">
            レーン未抽選だけ一括抽選
          </button>
        @endif
      </form>

      <div class="small text-muted mt-2">
        期日までに本人が抽選しなかった選手を、事務局側でまとめて処理するためのボタンです。<br>
        「未抽選者を一括抽選」は、シフト未抽選 → レーン未抽選の順で続けて処理します。
      </div>
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
          <th>優先出場</th>
          <th>参加権利</th>
          <th>希望シフト</th>
          <th>シフト</th>
          <th>レーン</th>
          <th>ボール数</th>
          <th>チェックイン</th>
          <th>状態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $entry)
          @php $bowler = $entry->bowler; @endphp
          <tr class="{{ $entry->is_priority_entry ? 'table-success' : '' }}">
            <td>{{ $bowler->license_no ?? '-' }}</td>
            <td>{{ $bowler->name_kanji ?? '-' }}</td>
            <td>
              @if ($entry->is_priority_entry)
                <div class="d-flex flex-column gap-1">
                  <div>
                    <span class="badge {{ $entry->priority_badge_class }}">
                      優先 {{ $entry->priority_order_label }}
                    </span>
                  </div>
                  <div class="small fw-bold">{{ $entry->priority_label }}</div>
                  <div class="small text-muted">{{ $entry->priority_source_label }}</div>
                </div>
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
            <td>
              <span class="badge bg-light text-dark" title="{{ $entry->eligibility_message }}">
                {{ $entry->eligibility_short }}
              </span>
            </td>
            <td>{{ $entry->preferred_shift_code ?? '-' }}</td>
            <td>
              @if ($tournament->use_shift_draw)
                @if ($entry->shift)
                  <span class="badge bg-info text-dark">{{ $entry->shift }}</span>
                @else
                  <span class="badge bg-warning text-dark">未抽選</span>
                @endif
              @else
                <span class="badge bg-light text-dark">運用なし</span>
              @endif
            </td>
            <td>
              @if ($tournament->use_lane_draw)
                @if ($entry->lane)
                  <span class="badge bg-secondary">{{ $entry->lane }}</span>
                @else
                  <span class="badge bg-warning text-dark">未抽選</span>
                @endif
              @else
                <span class="badge bg-light text-dark">運用なし</span>
              @endif
            </td>
            <td>{{ $entry->balls_count }}</td>
            <td>{{ optional($entry->checked_in_at)->format('Y-m-d H:i') ?? '-' }}</td>
            <td>
              @php
                $shiftPending = $tournament->use_shift_draw && blank($entry->shift);
                $lanePending = $tournament->use_lane_draw && blank($entry->lane);
              @endphp

              @if ($shiftPending || $lanePending)
                <span class="badge bg-warning text-dark">抽選未完了</span>
              @elseif (!$entry->checked_in_at)
                <span class="badge bg-light text-dark">抽選済 / 未チェックイン</span>
              @else
                <span class="badge bg-success">抽選済 / チェックイン済</span>
              @endif
            </td>
            <td>
              <form method="POST" action="{{ route('tournaments.entries.cancel', $entry->id) }}" class="m-0">
                @csrf
                <button type="submit"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('この参加登録を取り消します。抽選・チェックイン情報もクリアされます。よろしいですか？');">
                  取消
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="11" class="text-center text-muted">該当データはありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $entries->links() }}
</div>
@endsection
