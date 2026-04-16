@extends('layouts.app')

@section('content')
<style>
  .tournament-page {
    max-width: 1240px;
    margin: 0 auto;
  }

  .tournament-search {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
  }

  .tournament-search .form-control {
    min-width: 180px;
  }

  .tournament-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
  }

  .tournament-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .tournament-card {
    border: 1px solid #dee2e6;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
  }

  .tournament-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 18px 12px;
    border-bottom: 1px solid #eef1f4;
    background: #fafbfc;
  }

  .tournament-card-title-wrap {
    min-width: 0;
  }

  .tournament-card-title {
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.5;
    margin: 0;
    word-break: break-word;
  }

  .tournament-card-sub {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
  }

  .meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #dfe3e8;
    color: #495057;
    font-size: .82rem;
    line-height: 1.2;
    white-space: nowrap;
  }

  .tournament-card-body {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
    gap: 18px;
    padding: 16px 18px 18px;
  }

  .info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .info-box {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 12px 14px;
    background: #fff;
    min-width: 0;
  }

  .info-box-wide {
    grid-column: span 3;
  }

  .info-label {
    font-size: .78rem;
    color: #6c757d;
    margin-bottom: 8px;
    font-weight: 700;
    letter-spacing: .02em;
  }

  .info-value {
    color: #212529;
    font-size: .95rem;
    line-height: 1.55;
    word-break: break-word;
  }

  .date-stack {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .date-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .date-badge {
    display: inline-block;
    min-width: 42px;
    text-align: center;
    padding: 3px 8px;
    font-size: .74rem;
    font-weight: 700;
    border-radius: 999px;
    border: 1px solid #dfe3e8;
    background: #f8f9fa;
    color: #495057;
  }

  .action-panel {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .action-section {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 12px;
    background: #fff;
  }

  .action-title {
    font-size: .8rem;
    font-weight: 700;
    color: #6c757d;
    margin-bottom: 10px;
  }

  .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .action-buttons .btn,
  .action-buttons form {
    margin: 0;
  }

  .action-buttons form {
    display: inline-block;
  }

  .action-buttons .btn {
    flex: 0 0 auto;
  }

  .other-actions details {
    border: 1px dashed #ced4da;
    border-radius: 12px;
    padding: 10px 12px;
    background: #fcfcfd;
  }

  .other-actions summary {
    cursor: pointer;
    list-style: none;
    font-weight: 700;
    color: #495057;
  }

  .other-actions summary::-webkit-details-marker {
    display: none;
  }

  .other-actions summary::after {
    content: '＋';
    float: right;
    font-weight: 700;
  }

  .other-actions details[open] summary::after {
    content: '－';
  }

  .other-actions-body {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .empty-box {
    border: 1px dashed #ced4da;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    color: #6c757d;
    background: #fff;
  }

  @media (max-width: 1100px) {
    .tournament-card-body {
      grid-template-columns: 1fr;
    }

    .info-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .info-box-wide {
      grid-column: span 2;
    }
  }

  @media (max-width: 700px) {
    .tournament-search {
      align-items: stretch;
    }

    .tournament-search .form-control,
    .tournament-search .btn {
      width: 100%;
    }

    .tournament-toolbar .btn {
      width: 100%;
    }

    .tournament-card-header {
      flex-direction: column;
      align-items: stretch;
    }

    .info-grid {
      grid-template-columns: 1fr;
    }

    .info-box-wide {
      grid-column: span 1;
    }
  }
</style>

<div class="tournament-page">
  <h1 class="mb-4">大会一覧</h1>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="GET" action="{{ route('tournaments.index') }}" class="tournament-search">
    <input type="text" name="name" value="{{ request('name') }}" placeholder="大会名" class="form-control" style="width: 220px;">
    <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" style="width: 190px;">
    <input type="text" name="venue_name" value="{{ request('venue_name') }}" placeholder="会場名" class="form-control" style="width: 220px;">
    <button type="submit" class="btn btn-primary">検索</button>
    <a href="{{ route('tournaments.index') }}" class="btn btn-warning">リセット</a>
    <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
  </form>

  <div class="tournament-toolbar">
    <a href="{{ route('tournaments.create') }}" class="btn btn-success">新規登録</a>
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-primary btn-sm">大会使用ボール登録へ</a>
    <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary btn-sm">使用ボール一覧（管理）</a>
  </div>

  <div class="tournament-list">
    @forelse($tournaments as $tournament)
      <div class="tournament-card">
        <div class="tournament-card-header">
          <div class="tournament-card-title-wrap">
            <h2 class="tournament-card-title">{{ $tournament->name }}</h2>
            <div class="tournament-card-sub">
              <span class="meta-chip">ID: {{ $tournament->id }}</span>
              <span class="meta-chip">{{ optional($tournament->start_date)->format('Y') ?: '年未設定' }}</span>
              @if(!empty($tournament->venue_name))
                <span class="meta-chip">会場あり</span>
              @else
                <span class="meta-chip">会場未設定</span>
              @endif
            </div>
          </div>
        </div>

        <div class="tournament-card-body">
          <div class="info-grid">
            <div class="info-box">
              <div class="info-label">開催期間</div>
              <div class="info-value">
                <div class="date-stack">
                  <div class="date-row">
                    <span class="date-badge">開始</span>
                    <span>{{ optional($tournament->start_date)->format('Y-m-d') ?: '未設定' }}</span>
                  </div>
                  <div class="date-row">
                    <span class="date-badge">終了</span>
                    <span>{{ optional($tournament->end_date)->format('Y-m-d') ?: '未設定' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="info-box">
              <div class="info-label">申込期間</div>
              <div class="info-value">
                <div class="date-stack">
                  <div class="date-row">
                    <span class="date-badge">開始</span>
                    <span>{{ optional($tournament->entry_start)->format('Y-m-d H:i') ?: '未設定' }}</span>
                  </div>
                  <div class="date-row">
                    <span class="date-badge">締切</span>
                    <span>{{ optional($tournament->entry_end)->format('Y-m-d H:i') ?: '未設定' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="info-box">
              <div class="info-label">会場</div>
              <div class="info-value">
                <div class="fw-bold">{{ $tournament->venue_name ?: '未設定' }}</div>
                @if(!empty($tournament->venue_address))
                  <div class="text-muted mt-1" style="font-size:.85rem;">{{ $tournament->venue_address }}</div>
                @endif
              </div>
            </div>

            <div class="info-box info-box-wide">
              <div class="info-label">概要</div>
              <div class="info-value">
                大会編集・成績・配分・運用確認を、このカードからまとめて操作できます。
              </div>
            </div>
          </div>

          <div class="action-panel">
            <div class="action-section">
              <div class="action-title">主操作</div>
              <div class="action-buttons">
                <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-info btn-sm">詳細</a>
                <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-primary btn-sm">編集</a>
                <a href="{{ route('tournaments.clone', $tournament->id) }}" class="btn btn-outline-success btn-sm">コピー</a>
                <a href="{{ route('tournaments.results.index', $tournament->id) }}" class="btn btn-success btn-sm">成績一覧</a>
                <a href="{{ route('tournament_results.create', $tournament->id) }}" class="btn btn-outline-info btn-sm">成績入力</a>
                <a href="{{ route('tournaments.point_distributions.index', $tournament->id) }}" class="btn btn-outline-danger btn-sm">ポイント配分</a>
                <a href="{{ route('tournaments.prize_distributions.index', $tournament->id) }}" class="btn btn-outline-warning btn-sm">賞金配分</a>
              </div>
            </div>

            <div class="other-actions">
              <details>
                <summary>その他の操作</summary>
                <div class="other-actions-body">
                  @if(auth()->user()?->isAdmin())
                    <a href="{{ route('admin.tournaments.draw.settings', $tournament->id) }}" class="btn btn-outline-primary btn-sm">運営設定</a>
                  @endif

                  <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-dark btn-sm">エントリー一覧</a>
                  <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-dark btn-sm">抽選一覧</a>
                  <a href="{{ route('tournaments.operation_logs.index', $tournament->id) }}" class="btn btn-outline-info btn-sm">運用ログ</a>
                  <a href="{{ route('tournaments.draws.index', ['tournament' => $tournament->id, 'pending_draw' => 1]) }}" class="btn btn-outline-secondary btn-sm">未抽選</a>

                  @if(auth()->user()?->isAdmin() || auth()->user()?->isEditor())
                    <form method="POST" action="{{ route('tournaments.participant_group.create', $tournament->id) }}">
                      @csrf
                      <button class="btn btn-outline-dark btn-sm" title="この大会の参加者グループを作成">参加者グループ</button>
                    </form>
                  @endif

                  @if(auth()->user()?->isAdmin())
                    <form method="POST"
                          action="{{ route('admin.tournaments.destroy', $tournament->id) }}"
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
              </details>
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="empty-box">該当する大会はありません。</div>
    @endforelse
  </div>
</div>
@endsection