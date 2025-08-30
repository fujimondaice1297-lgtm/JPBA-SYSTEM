@extends('layouts.app')

@section('content')
<div class="container" style="max-width:420px">
  <h2 class="mb-3">ログイン</h2>

  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $e)
        <div>{{ $e }}</div>
      @endforeach
    </div>
  @endif

  {{-- A案：ルートに名前を付けた場合（上の ①A） --}}
  <form method="POST" action="{{ route('login.attempt') }}">
  {{-- B案：名前を付けない場合（上の ①B）
  <form method="POST" action="{{ url('/login') }}">
  --}}
    @csrf

    <div class="mb-3">
      <label class="form-label">メール or ライセンスNo.</label>
      <input
        name="login"
        type="text"                           {{-- ← email ではなく text に！ --}}
        class="form-control @error('login') is-invalid @enderror"
        value="{{ old('login') }}"
        required
        autofocus
        placeholder="例）user@example.com / m00001234"
      >
      @error('login') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label">パスワード</label>
      <input
        name="password"
        type="password"
        class="form-control @error('password') is-invalid @enderror"
        required
        autocomplete="current-password"
      >
      @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
      <label class="form-check-label" for="remember">ログイン状態を保持</label>
    </div>

    <button class="btn btn-primary w-100">ログイン</button>

    <div class="mt-3 d-flex justify-content-between">
      <a href="{{ route('password.request') }}">パスワードをお忘れですか？</a>
      <a href="{{ route('register') }}">新規登録</a>
    </div>
  </form>
</div>
@endsection
