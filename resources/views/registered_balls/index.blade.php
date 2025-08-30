@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-3">プロ登録ボール一覧</h2>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- ナビ --}}
  <div class="mb-3 d-flex flex-wrap gap-2">
    <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ</a>
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
    <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary">承認ボールリストへ戻る</a>
    <a href="{{ route('athlete.index') }}" class="btn btn-outline-secondary">インデックスへ戻る</a>
  </div>

  {{-- 検索 --}}
  <form method="GET" action="{{ route('registered_balls.index') }}" class="row g-2 mb-3">
    <div class="col-md-3">
      <label class="form-label">プロライセンス番号</label>
      <input type="text" name="license_no" value="{{ request('license_no') }}" class="form-control" placeholder="M00001234">
    </div>
    <div class="col-md-3">
      <label class="form-label">プロ名（漢字）</label>
      <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="山田 太郎">
    </div>
    <div class="col-md-2">
      <label class="form-label">検量証の有無</label>
      <select name="has_certificate" class="form-select">
        <option value="">検量証で絞り込み</option>
        <option value="1" @selected(request('has_certificate')==='1')>あり</option>
        <option value="0" @selected(request('has_certificate')==='0')>なし（仮登録）</option>
      </select>
    </div>
    <div class="col-md-4 d-flex align-items-end gap-2">
      <button type="submit" class="btn btn-primary">検索</button>
      <a href="{{ route('registered_balls.index') }}" class="btn btn-warning">リセット</a>
      <a href="{{ route('registered_balls.create') }}" class="btn btn-success ms-auto">+ 本登録を新規作成</a>
    </div>
  </form>

  {{-- 一覧 --}}
  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>プロライセンス</th>
          <th>プロ名</th>
          <th>メーカー名</th>
          <th>ボール名</th>
          <th>シリアルNo</th>
          <th>登録日</th>
          <th>有効期限</th>
          <th>検量証番号 / 状態</th>
          <th style="width:160px;">操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse($balls as $row)
          <tr>
            <td>{{ $row['id'] }}</td>
            <td>{{ $row['license_no'] ?? '―' }}</td>
            <td>{{ $row['name_kanji'] ?? '―' }}</td>
            <td>{{ $row['manufacturer'] ?: '―' }}</td>
            <td>{{ $row['ball_name'] ?: '―' }}</td>
            <td>{{ $row['serial_number'] }}</td>
            <td>{{ optional($row['registered_at'])->format('Y-m-d') }}</td>
            <td>{{ optional($row['expires_at'])->format('Y-m-d') ?? '―' }}</td>
            <td>
              @if($row['inspection_number'])
                <span class="badge bg-info me-1">検量証OK</span>
                <span class="text-muted">{{ $row['inspection_number'] }}</span>
              @else
                <span class="badge bg-warning text-dark">仮登録</span>
              @endif
            </td>
            <td class="d-flex gap-1">
              @if($row['source']==='registered')
                {{-- 本登録の編集/削除 --}}
                <a href="{{ route('registered_balls.edit', $row['_model']->id) }}" class="btn btn-sm btn-outline-primary">編集</a>
                <form action="{{ route('registered_balls.destroy', $row['_model']->id) }}" method="POST" onsubmit="return confirm('削除しますか？')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">削除</button>
                </form>
              @else
                {{-- 仮登録（used_balls）→ 本登録へ誘導（項目をある程度プリフィル） --}}
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
                {{-- 参考：仮登録自体を延長したい等があれば used_balls 側の編集画面を用意してリンク --}}
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-muted">データがありません。</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-center">
    {{ $balls->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection
