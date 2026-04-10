
@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-3">プロ登録ボール一覧</h2>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="mb-3 d-flex flex-wrap gap-2">
    <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary">使用ボール一覧へ</a>
    <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ</a>
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
    <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary">承認ボールリストへ戻る</a>
    <a href="{{ route('athlete.index') }}" class="btn btn-outline-secondary">インデックスへ戻る</a>
  </div>

  <div class="alert alert-light border mb-3">
    <div class="row g-2 small">
      <div class="col-md-2">総件数: <strong>{{ $summary['total'] ?? 0 }}</strong></div>
      <div class="col-md-2">本登録: <strong>{{ $summary['registered'] ?? 0 }}</strong></div>
      <div class="col-md-2">仮登録: <strong>{{ $summary['used'] ?? 0 }}</strong></div>
      <div class="col-md-2">有効: <strong>{{ $summary['valid'] ?? 0 }}</strong></div>
      <div class="col-md-2">期限間近: <strong>{{ $summary['expiring_soon'] ?? 0 }}</strong></div>
      <div class="col-md-2">期限切れ: <strong>{{ $summary['expired'] ?? 0 }}</strong></div>
    </div>
  </div>

  <form method="GET" action="{{ route('registered_balls.index') }}" class="row g-2 mb-3">
    <div class="col-md-2">
      <label class="form-label">プロライセンス番号</label>
      <input type="text" name="license_no" value="{{ request('license_no') }}" class="form-control" placeholder="M00001234">
    </div>

    <div class="col-md-2">
      <label class="form-label">プロ名（漢字）</label>
      <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="山田 太郎">
    </div>

    <div class="col-md-2">
      <label class="form-label">検量証の有無</label>
      <select name="has_certificate" class="form-select">
        <option value="">検量証で絞り込み</option>
        <option value="1" @selected(request('has_certificate') === '1')>あり</option>
        <option value="0" @selected(request('has_certificate') === '0')>なし</option>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">表示元</label>
      <select name="source" class="form-select">
        <option value="">すべて</option>
        <option value="registered" @selected(request('source') === 'registered')>本登録のみ</option>
        <option value="used" @selected(request('source') === 'used')>仮登録のみ</option>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">状態</label>
      <select name="status" class="form-select">
        <option value="">すべて</option>
        <option value="valid" @selected(request('status') === 'valid')>有効</option>
        <option value="expiring_soon" @selected(request('status') === 'expiring_soon')>期限間近</option>
        <option value="expired" @selected(request('status') === 'expired')>期限切れ</option>
        <option value="provisional" @selected(request('status') === 'provisional')>仮登録 / 検量証待ち</option>
      </select>
    </div>

    <div class="col-md-2 d-flex align-items-end gap-2">
      <button type="submit" class="btn btn-primary">検索</button>
      <a href="{{ route('registered_balls.index') }}" class="btn btn-warning">リセット</a>
    </div>

    <div class="col-12 d-flex justify-content-end">
      <a href="{{ route('registered_balls.create') }}" class="btn btn-success">+ 本登録を新規作成</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>表示元</th>
          <th>プロライセンス</th>
          <th>プロ名</th>
          <th>メーカー名</th>
          <th>ボール名</th>
          <th>シリアルNo</th>
          <th>登録日</th>
          <th>有効期限</th>
          <th>検量証番号 / 状態</th>
          <th>修正導線</th>
          <th style="width:180px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse($balls as $row)
          <tr>
            <td>{{ $row['id'] }}</td>
            <td>
              <span class="badge bg-{{ $row['source'] === 'registered' ? 'primary' : 'warning text-dark' }}">
                {{ $row['source_label'] }}
              </span>
            </td>
            <td>{{ $row['license_no'] ?? '―' }}</td>
            <td>{{ $row['name_kanji'] ?? '―' }}</td>
            <td>{{ $row['manufacturer'] ?: '―' }}</td>
            <td>{{ $row['ball_name'] ?: '―' }}</td>
            <td>{{ $row['serial_number'] }}</td>
            <td>{{ optional($row['registered_at'])->format('Y-m-d') }}</td>
            <td>
              @if($row['expires_at'])
                <div>{{ optional($row['expires_at'])->format('Y-m-d') }}</div>
                @if(!is_null($row['days_to_expire']))
                  <small class="text-muted">
                    @if($row['days_to_expire'] < 0)
                      {{ abs($row['days_to_expire']) }}日経過
                    @else
                      残り{{ $row['days_to_expire'] }}日
                    @endif
                  </small>
                @endif
              @else
                <span class="text-muted">―</span>
              @endif
            </td>
            <td>
              @if($row['inspection_number'])
                <div class="mb-1">{{ $row['inspection_number'] }}</div>
              @else
                <div class="text-muted mb-1">（なし）</div>
              @endif
              <span class="badge bg-{{ $row['status_badge'] }}">{{ $row['status_label'] }}</span>
            </td>
            <td>
              @if($row['source'] === 'used')
                <span class="text-muted">本登録へ移してください</span>
              @elseif($row['status_key'] === 'provisional')
                <span class="text-muted">検量証番号を入力してください</span>
              @elseif($row['status_key'] === 'expired')
                <span class="text-muted">再検量後に更新してください</span>
              @elseif($row['status_key'] === 'expiring_soon')
                <span class="text-muted">期限前に更新を推奨</span>
              @else
                <span class="text-muted">このまま有効です</span>
              @endif
            </td>
            <td class="d-flex gap-1 flex-wrap">
              @if($row['source'] === 'registered')
                <a href="{{ route('registered_balls.edit', $row['_model']->id) }}" class="btn btn-sm btn-outline-primary">
                  {{ $row['status_key'] === 'expired' ? '再検量更新' : ($row['status_key'] === 'provisional' ? '検量証登録' : '編集') }}
                </a>

                @if(auth()->user()?->isAdmin())
                  <form action="{{ route('admin.registered_balls.destroy', $row['_model']->id) }}"
                        method="POST" onsubmit="return confirm('削除しますか？')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">削除</button>
                  </form>
                @endif
              @else
                <a
                  href="{{ route('registered_balls.create', [
                        'license_no'       => $row['license_no'],
                        'approved_ball_id' => optional($row['_model']->approvedBall)->id,
                        'serial_number'    => $row['serial_number'],
                        'registered_at'    => optional($row['registered_at'])->format('Y-m-d'),
                  ]) }}"
                  class="btn btn-sm btn-primary"
                >
                  本登録へ
                </a>
                <a href="{{ route('used_balls.edit', $row['_model']->id) }}" class="btn btn-sm btn-outline-secondary">仮登録更新</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="12" class="text-center text-muted">データがありません。</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-center">
    {{ $balls->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection
