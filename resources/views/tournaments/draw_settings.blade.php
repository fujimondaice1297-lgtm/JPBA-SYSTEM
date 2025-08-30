@extends('layouts.app')
@section('content')
<div class="container">
  <h2>抽選設定：{{ $tournament->name }}</h2>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

  <form method="POST" action="{{ route('admin.tournaments.draw.settings.save',$tournament->id) }}" class="row g-3">
    @csrf

    <div class="col-md-6">
      <label class="form-label">シフト候補（カンマ区切り）</label>
      <input type="text" name="shift_codes" class="form-control"
             placeholder="例: A,B,C"
             value="{{ old('shift_codes', $tournament->shift_codes) }}">
    </div>

    <div class="col-md-3">
      <label class="form-label">シフト抽選 開始</label>
      <input type="datetime-local" name="shift_draw_open_at" class="form-control"
             value="{{ old('shift_draw_open_at', optional($tournament->shift_draw_open_at)->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-3">
      <label class="form-label">シフト抽選 終了</label>
      <input type="datetime-local" name="shift_draw_close_at" class="form-control"
             value="{{ old('shift_draw_close_at', optional($tournament->shift_draw_close_at)->format('Y-m-d\TH:i')) }}">
    </div>

    <div class="col-md-3">
      <label class="form-label">レーン抽選 開始</label>
      <input type="datetime-local" name="lane_draw_open_at" class="form-control"
             value="{{ old('lane_draw_open_at', optional($tournament->lane_draw_open_at)->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-3">
      <label class="form-label">レーン抽選 終了</label>
      <input type="datetime-local" name="lane_draw_close_at" class="form-control"
             value="{{ old('lane_draw_close_at', optional($tournament->lane_draw_close_at)->format('Y-m-d\TH:i')) }}">
    </div>

    <div class="col-md-3">
      <label class="form-label">レーン開始</label>
      <input type="number" name="lane_from" class="form-control"
             value="{{ old('lane_from', $tournament->lane_from) }}">
    </div>
    <div class="col-md-3">
      <label class="form-label">レーン終了</label>
      <input type="number" name="lane_to" class="form-control"
             value="{{ old('lane_to', $tournament->lane_to) }}">
    </div>

    <div class="col-12">
      <button class="btn btn-primary">保存</button>
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>
</div>
@endsection
