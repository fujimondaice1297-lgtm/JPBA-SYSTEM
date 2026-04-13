@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">未抽選者DM送信</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-secondary">抽選一覧へ戻る</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
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
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">対象候補</div>
        <div class="fs-4 fw-bold">{{ $entries->count() }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">メール送信可能</div>
        <div class="fs-4 fw-bold">{{ $mailReadyEntries->count() }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">抽出種別</div>
        <div class="fs-4 fw-bold">{{ $pendingType }}</div>
      </div></div>
    </div>
  </div>

  <form method="POST" action="{{ route('tournaments.draw_reminders.store', $tournament->id) }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header fw-bold">送信設定</div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">送信対象</label>
          <select name="pending_type" class="form-select">
            <option value="either" {{ old('pending_type', $pendingType) === 'either' ? 'selected' : '' }}>未抽選すべて</option>
            <option value="shift" {{ old('pending_type', $pendingType) === 'shift' ? 'selected' : '' }}>シフト未抽選</option>
            <option value="lane" {{ old('pending_type', $pendingType) === 'lane' ? 'selected' : '' }}>レーン未抽選</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">差出人メール</label>
          <input type="email" name="from_address" class="form-control"
                 value="{{ old('from_address', $defaults['from_address']) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">差出人名</label>
          <input type="text" name="from_name" class="form-control"
                 value="{{ old('from_name', $defaults['from_name']) }}">
        </div>

        <div class="col-md-2">
          <label class="form-label">テスト送信先</label>
          <input type="email" name="dry_run_to" class="form-control"
                 value="{{ old('dry_run_to') }}"
                 placeholder="任意">
        </div>

        <div class="col-12">
          <label class="form-label">件名</label>
          <input type="text" name="subject" class="form-control"
                 value="{{ old('subject', $defaults['subject']) }}">
        </div>

        <div class="col-12">
          <label class="form-label">本文</label>
          <textarea name="body" class="form-control" rows="10">{{ old('body', $defaults['body']) }}</textarea>
          <small class="text-muted">
            利用可能トークン：{name} / {tournament} / {pending_items} / {preferred_shift} / {entry_url}
          </small>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mb-4">
      <button type="submit" class="btn btn-primary">送信する</button>
      <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>

  <div class="card">
    <div class="card-header fw-bold">対象プレビュー（先頭50件）</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th>ライセンスNo</th>
              <th>氏名</th>
              <th>メール</th>
              <th>希望シフト</th>
              <th>シフト</th>
              <th>レーン</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($mailReadyEntries->take(50) as $entry)
              <tr>
                <td>{{ $entry->bowler?->license_no ?? '-' }}</td>
                <td>{{ $entry->bowler?->name_kanji ?? '-' }}</td>
                <td>{{ $entry->bowler?->email ?? '-' }}</td>
                <td>{{ $entry->preferred_shift_code ?? '-' }}</td>
                <td>{{ $entry->shift ?? '-' }}</td>
                <td>{{ $entry->lane ?? '-' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">送信可能な対象はありません。</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection