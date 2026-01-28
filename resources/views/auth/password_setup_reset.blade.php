{{-- resources/views/auth/password_setup_reset.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 520px;">
    <h2 class="mb-3">パスワード設定</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label">メールアドレス</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label">新しいパスワード（10文字以上）</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">新しいパスワード（確認）</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">設定する</button>
    </form>
</div>
@endsection
