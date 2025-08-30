@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-4">新規ユーザー登録</h2>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>入力エラー：</strong>
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('register') }}">
    @csrf

    <div class="row">
      <div class="mb-3">
        <label>ライセンス番号</label>
        <input type="text" name="license_no" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>メールアドレス</label>
        <input type="email" name="email" class="form-control" required>
    </div>

      <div class="col-md-6 mb-3">
        <label>パスワード</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="col-md-6 mb-3">
        <label>パスワード（確認）</label>
        <input type="password" name="password_confirmation" class="form-control" required>
      </div>

      <div class="col-12 mt-3">
        <button type="submit" class="btn btn-primary">登録</button>
        <a href="{{ route('login') }}" class="btn btn-secondary">ログイン画面へ戻る</a>
      </div>
    </div>
  </form>
</div>
@endsection
