@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-4">パスワード変更</h2>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('password.update.self') }}">
    @csrf

    <div class="mb-3">
      <label>現在のパスワード</label>
      <input type="password" name="current_password" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>新しいパスワード</label>
      <input type="password" name="new_password" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>新しいパスワード（確認）</label>
      <input type="password" name="new_password_confirmation" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">変更する</button>
  </form>
</div>
@endsection
