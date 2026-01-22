@extends('layouts.app')

@section('content')
<div class="text-center py-5">
  <h1 class="display-6">権限がありません（403）</h1>
  <p class="text-muted">このページにはアクセスできません。</p>
  <div class="d-flex gap-2 justify-content-center">
    <a href="{{ route('member.dashboard') }}" class="btn btn-primary">マイページへ戻る</a>
    @if(auth()->user()?->isAdmin())
      <a href="{{ route('admin.home') }}" class="btn btn-outline-danger">管理画面へ</a>
    @endif
  </div>
</div>
@endsection
