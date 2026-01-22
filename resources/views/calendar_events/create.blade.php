{{-- resources/views/calendar_events/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container" style="max-width:720px">
  <h2 class="mb-3">カレンダー手入力（大会以外）</h2>

  {{-- 成功メッセージ --}}
  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  {{-- バリデーションエラー --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">入力エラーがあります。</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('calendar_events.store') }}">
    @csrf

    <div class="mb-3">
      <label class="form-label">種別</label>
      <select name="kind" class="form-select" required>
        @php $kindOld = old('kind', $e->kind ?? 'other'); @endphp
        <option value="pro_test" {{ $kindOld==='pro_test' ? 'selected' : '' }}>プロテスト</option>
        <option value="approved" {{ $kindOld==='approved' ? 'selected' : '' }}>承認大会</option>
        <option value="other"    {{ $kindOld==='other'    ? 'selected' : '' }}>その他</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">タイトル</label>
      <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">開始日</label>
        {{-- ネイティブ date 入力。スラッシュで来てもサーバ側で吸収するので安心してどうぞ --}}
        <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">終了日</label>
        <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}" required>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">会場</label>
      <input type="text" name="venue" class="form-control" value="{{ old('venue') }}">
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary">登録</button>
      <a href="{{ url()->previous() }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>
</div>
@endsection
