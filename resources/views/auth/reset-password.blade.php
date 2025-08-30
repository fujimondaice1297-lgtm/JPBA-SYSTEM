@extends('layouts.app')

@section('content')
<div class="container">
  <h2>新しいパスワードの設定</h2>

  <form method="POST" action="{{ route('password.update') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
      <label>メールアドレス</label>
      <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>新しいパスワード</label>
      <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>パスワード（確認）</label>
      <input type="password" name="password_confirmation" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-success">パスワードを更新</button>
  </form>
</div>
@endsection
