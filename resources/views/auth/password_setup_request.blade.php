{{-- resources/views/auth/password_setup_request.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 520px;">
    <h2 class="mb-3">初期パスワード設定</h2>
    <p class="text-muted">登録済みのメールアドレスを入力してください。該当する場合、初期パスワード設定用のリンクを送信します。</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">登録メールアドレス</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary w-100">送信</button>
    </form>

    <div class="mt-3">
        <a href="{{ route('login') }}">ログイン画面へ戻る</a>
    </div>
</div>
@endsection
