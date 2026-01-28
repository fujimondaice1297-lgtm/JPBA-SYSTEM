@extends('layouts.app')

@section('content')
<div class="container">
  <h2>新しいパスワードの設定</h2>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('password.update') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    @php
      $prefilledEmail = old('email', $email ?? '');
    @endphp

    <div class="mb-3">
      <label class="form-label">メールアドレス</label>
      <input
        type="email"
        name="email"
        class="form-control"
        value="{{ $prefilledEmail }}"
        required
        autocomplete="email"
        {{ !empty($email) ? 'readonly' : '' }}
      >
      @if (!empty($email))
        <small class="text-muted">※リンクのメールアドレスと一致させるため、この画面では編集不可にしています。</small>
      @endif
    </div>

    <div class="mb-3">
      <label class="form-label">新しいパスワード</label>
      <input type="password" name="password" class="form-control" required autocomplete="new-password">
    </div>

    <div class="mb-3">
      <label class="form-label">パスワード（確認）</label>
      <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-success">パスワードを更新</button>
  </form>
</div>
@endsection
