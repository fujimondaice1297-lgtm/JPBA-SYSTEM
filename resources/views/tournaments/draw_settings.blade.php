@extends('layouts.app')
@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">運営 / 抽選設定</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-outline-primary">大会編集</a>
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ戻る</a>
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

  <form method="POST" action="{{ route('admin.tournaments.draw.settings.save', $tournament->id) }}" class="row g-3">
    @csrf

    <div class="col-12">
      <div class="card">
        <div class="card-header fw-bold">シフト運用</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label d-block">シフト抽選を使う</label>
            <input type="hidden" name="use_shift_draw" value="0">
            <input type="checkbox" name="use_shift_draw" value="1" class="form-check-input"
                   {{ old('use_shift_draw', $tournament->use_shift_draw) ? 'checked' : '' }}>
          </div>

          <div class="col-md-5">
            <label class="form-label">シフト候補（カンマ区切り）</label>
            <input type="text" name="shift_codes" class="form-control"
                   placeholder="例: A,B,C"
                   value="{{ old('shift_codes', $tournament->shift_codes) }}">
          </div>

          <div class="col-md-4">
            <label class="form-label d-block">希望シフト受付</label>
            <input type="hidden" name="accept_shift_preference" value="0">
            <input type="checkbox" name="accept_shift_preference" value="1" class="form-check-input"
                   {{ old('accept_shift_preference', $tournament->accept_shift_preference) ? 'checked' : '' }}>
            <small class="text-muted d-block">会員エントリー時に希望シフトを受け付けます。</small>
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
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header fw-bold">レーン運用</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label d-block">レーン抽選を使う</label>
            <input type="hidden" name="use_lane_draw" value="0">
            <input type="checkbox" name="use_lane_draw" value="1" class="form-check-input"
                   {{ old('use_lane_draw', $tournament->use_lane_draw) ? 'checked' : '' }}>
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

          <div class="col-md-3">
            <label class="form-label">割付方式</label>
            <select name="lane_assignment_mode" class="form-select">
              @php $laneMode = old('lane_assignment_mode', $tournament->lane_assignment_mode ?? 'single_lane'); @endphp
              <option value="single_lane" {{ $laneMode === 'single_lane' ? 'selected' : '' }}>通常レーン割付</option>
              <option value="box" {{ $laneMode === 'box' ? 'selected' : '' }}>BOX運用</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">1BOX人数</label>
            <input type="number" name="box_player_count" class="form-control"
                   value="{{ old('box_player_count', $tournament->box_player_count) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">奇数レーン人数</label>
            <input type="number" name="odd_lane_player_count" class="form-control"
                   value="{{ old('odd_lane_player_count', $tournament->odd_lane_player_count) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">偶数レーン人数</label>
            <input type="number" name="even_lane_player_count" class="form-control"
                   value="{{ old('even_lane_player_count', $tournament->even_lane_player_count) }}">
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

          <div class="col-12">
            <div class="small text-muted">
              BOX運用では、奇数レーン人数 + 偶数レーン人数 = 1BOX人数 にしてください。<br>
              例: 5番レーン2名 / 6番レーン3名 / BOX5名
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary">保存</button>
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>
</div>
@endsection