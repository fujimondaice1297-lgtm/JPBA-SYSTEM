@extends('layouts.app')
@section('content')
<div class="container">
  <h2>ボール紐付けテスト</h2>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="POST" id="attach-form"
        action="{{ route('web.tournament_entries.balls.store', ['entry' => $entryId ?? 0]) }}">
      @csrf

      <div class="mb-3">
          <label class="form-label">Entry ID</label>
          <input name="entry_id" class="form-control"
                value="{{ request('entry_id') }}"
                oninput="updateAction(this.value)">
      </div>

      <div class="mb-3">
          <label class="form-label">Used Ball ID</label>
          <input name="used_ball_id" class="form-control" value="{{ old('used_ball_id') }}">
      </div>

      <button class="btn btn-primary">紐付け</button>
  </form>

    <script>
    function updateAction(entryId){
      const f = document.getElementById('attach-form');
      // 末尾 '/{数字}/balls' を剥がして作り直す
      const base = f.action.replace(/\/\d+\/balls$/, '');
      const id   = String(entryId || 0).replace(/\D/g, ''); // 数字以外を除去
      f.action   = base + '/' + id + '/balls';
    }
    </script>

</div>
@endsection
