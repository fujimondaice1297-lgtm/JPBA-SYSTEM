@extends('layouts.app')

@section('content')
<div class="container">
  <h2>パスワード再設定</h2>
  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="mb-3">
      <label>メールアドレス</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">リセットリンクを送信</button>
  </form>
</div>
@endsection
