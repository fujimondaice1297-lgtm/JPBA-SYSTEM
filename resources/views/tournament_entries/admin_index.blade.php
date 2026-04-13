@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">大会エントリー一覧（管理）</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ戻る</a>
      <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-dark">抽選一覧</a>
      <a href="{{ route('tournaments.draws.index', ['tournament' => $tournament->id, 'pending_draw' => 1]) }}" class="btn btn-outline-secondary">未抽選一覧</a>
      <a href="{{ route('member.tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-primary">参加選手向け一覧</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>入力内容に誤りがあります：</strong>
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">参加</div>
        <div class="fs-4 fw-bold">{{ $summary['entry_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">ウェイティング</div>
        <div class="fs-4 fw-bold">{{ $summary['waitlist_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">チェックイン済み</div>
        <div class="fs-4 fw-bold">{{ $summary['checked_in_count'] }}</div>
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
  </div>

  <div class="card mb-4">
    <div class="card-header fw-bold">ウェイティング登録</div>
    <div class="card-body">
      <form method="POST" action="{{ route('tournaments.waitlist.store', $tournament->id) }}">
        @csrf
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">ライセンスNo</label>
            <input type="text" name="license_no" value="{{ old('license_no') }}" class="form-control" placeholder="例: F00000003">
          </div>
          <div class="col-md-2">
            <label class="form-label">優先順</label>
            <input type="number" name="waitlist_priority" value="{{ old('waitlist_priority') }}" class="form-control" min="1" max="9999">
          </div>
          <div class="col-md-5">
            <label class="form-label">備考</label>
            <input type="text" name="waitlist_note" value="{{ old('waitlist_note') }}" class="form-control" maxlength="2000" placeholder="例: 権利外だが欠員時に繰り上げ候補">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">ウェイティング登録</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <form method="GET" action="{{ route('tournaments.entries.index', $tournament->id) }}" class="mb-3">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">検索</label>
        <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="ライセンスNo / 氏名 / フリガナ">
      </div>
      <div class="col-md-3">
        <label class="form-label">状態</label>
        <select name="status" class="form-select">
          <option value="active" {{ $status === 'active' ? 'selected' : '' }}>参加 + ウェイティング</option>
          <option value="entry" {{ $status === 'entry' ? 'selected' : '' }}>参加のみ</option>
          <option value="waiting" {{ $status === 'waiting' ? 'selected' : '' }}>ウェイティングのみ</option>
          <option value="no_entry" {{ $status === 'no_entry' ? 'selected' : '' }}>不参加のみ</option>
        </select>
      </div>
      <div class="col-md-5 d-flex gap-2">
        <button type="submit" class="btn btn-primary">絞り込む</button>
        <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-secondary">リセット</a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th>状態</th>
          <th>優先順</th>
          <th>ライセンスNo</th>
          <th>氏名</th>
          <th>参加権利</th>
          <th>シフト</th>
          <th>レーン</th>
          <th>ボール数</th>
          <th>チェックイン</th>
          <th>備考</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $entry)
          @php
            $bowler = $entry->bowler;
          @endphp
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
            <td>{{ $entry->waitlist_priority ?? '-' }}</td>
            <td>{{ $bowler->license_no ?? '-' }}</td>
            <td>{{ $bowler->name_kanji ?? '-' }}</td>
            <td>
              <span class="badge bg-light text-dark" title="{{ $entry->eligibility_message }}">
                {{ $entry->eligibility_short }}
              </span>
            </td>
            <td>{{ $entry->shift ?? '-' }}</td>
            <td>{{ $entry->lane ?? '-' }}</td>
            <td>{{ $entry->balls_count }}</td>
            <td>{{ optional($entry->checked_in_at)->format('Y-m-d H:i') ?? '-' }}</td>
            <td class="small">{{ $entry->waitlist_note ?? '-' }}</td>
            <td>
              <div class="d-flex flex-wrap gap-2">
                @if ($entry->status === 'waiting')
                  <form method="POST" action="{{ route('tournaments.waitlist.promote', $entry->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">参加へ繰り上げ</button>
                  </form>
                @endif

                <a href="{{ route('tournaments.draws.index', ['tournament' => $tournament->id, 'q' => $bowler->license_no ?? '']) }}"
                   class="btn btn-sm btn-outline-secondary">
                  抽選状況
                </a>
              </div>
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