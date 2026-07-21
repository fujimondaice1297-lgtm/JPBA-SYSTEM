@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 920px;">
  <h1 class="h3 mb-3">大会からテンプレートを作成</h1>

  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <form method="POST" action="{{ route('tournament_templates.store') }}">
    @csrf

    <div class="mb-3">
      <label class="form-label">設定元大会</label>
      <select name="source_tournament_id" class="form-select" required>
        <option value="">選択してください</option>
        @foreach($tournaments as $tournament)
          <option value="{{ $tournament->id }}" {{ (string) old('source_tournament_id', $selectedTournamentId) === (string) $tournament->id ? 'selected' : '' }}>
            {{ $tournament->year }} {{ $tournament->name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">既存テンプレートへ新版を追加</label>
      <select name="tournament_template_id" class="form-select">
        <option value="">新しいテンプレートとして保存</option>
        @foreach($templates as $template)
          <option value="{{ $template->id }}" {{ (string) old('tournament_template_id', request('tournament_template_id')) === (string) $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="row">
      <div class="col-md-8 mb-3">
        <label class="form-label">テンプレート名</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="例：シーズントライアル標準8名シュートアウト" required>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">管理コード</label>
        <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="未入力なら自動生成">
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">既存の大会シリーズ</label>
        <select name="tournament_series_id" class="form-select">
          <option value="">単発・共通テンプレート</option>
          @foreach($seriesList as $series)
            <option value="{{ $series->id }}" {{ (string) old('tournament_series_id') === (string) $series->id ? 'selected' : '' }}>{{ $series->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">新しい大会シリーズ名</label>
        <input type="text" name="new_series_name" class="form-control" value="{{ old('new_series_name') }}" placeholder="例：JPBAシーズントライアル">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">開催周期</label>
      <select name="recurrence_type" class="form-select">
        <option value="annual">毎年</option>
        <option value="seasonal">年に複数回</option>
        <option value="irregular">不定期</option>
        <option value="one_off">単年</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">説明</label>
      <textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">版の変更内容</label>
      <textarea name="change_note" rows="2" class="form-control">{{ old('change_note') }}</textarea>
    </div>

    <div class="alert alert-light border">
      選手、エントリー、レーン結果、スコア、正式成績、タイトル明細はテンプレートへ保存されません。
    </div>

    <div class="d-flex gap-2 justify-content-end">
      <a href="{{ route('tournament_templates.index') }}" class="btn btn-outline-secondary">戻る</a>
      <button type="submit" class="btn btn-primary">テンプレートを保存</button>
    </div>
  </form>
</div>
@endsection
