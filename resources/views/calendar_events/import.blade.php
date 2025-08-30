@extends('layouts.app')
@section('content')
<div class="container" style="max-width:720px">
  <h2>CSV/TSV インポート（手入力イベント）</h2>
  @if($errors->any()) <div class="alert alert-danger">@foreach($errors->all() as $er)<div>{{ $er }}</div>@endforeach</div>@endif
  @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
  @if(session('import_errors'))
    <div class="alert alert-warning">
      <div>行エラー（最大10件表示）</div>
      <ul class="mb-0">
        @foreach(array_slice(session('import_errors'),0,10) as $er)
          <li>{{ $er }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('calendar_events.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
      <label class="form-label">ファイル（CSV/TSV）</label>
      <input type="file" name="file" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">区切り文字</label>
      <select name="delimiter" class="form-select">
        <option value=",">カンマ (,)</option>
        <option value=";">セミコロン (;)</option>
        <option value="\t">タブ (TSV)</option>
      </select>
    </div>
    <div class="mb-3">
      <div class="small text-muted">ヘッダ例: <code>title,start_date,end_date,venue,kind</code></div>
      <div class="small text-muted">kind は <code>pro_test / approved / other</code></div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">アップロード</button>
      <a href="{{ route('calendar_events.index') }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>
</div>
@endsection
