// resources/views/calendar_events/create.blade.php
@extends('layouts.app')
@section('content')
<div class="container" style="max-width:720px">
  <h2>カレンダー手入力（大会以外）</h2>
  <form method="POST" action="{{ route('calendar_events.store') }}">
    @csrf
    <div class="mb-3">
      <label class="form-label">種別</label>
      <select name="kind" class="form-select" required>
        <option value="pro_test">プロテスト</option>
        <option value="approved">承認大会</option>
        <option value="other">その他</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">タイトル</label>
      <input type="text" name="title" class="form-control" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">開始日</label>
        <input type="date" name="start_date" class="form-control" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">終了日</label>
        <input type="date" name="end_date" class="form-control" required>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">会場</label>
      <input type="text" name="venue" class="form-control">
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">登録</button>
      <a href="{{ route('calendar.annual') }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>
</div>
@endsection
