@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">会場の新規登録</h1>

@if ($errors->any())
  <div class="alert alert-danger">
    <strong>入力内容に誤りがあります：</strong>
    <ul class="mb-0 mt-2">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('venues.store') }}">
  @csrf
  @include('venues._form', ['venue' => $venue])

  <div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary">登録</button>
    <a href="{{ route('venues.index') }}" class="btn btn-secondary">一覧へ戻る</a>
  </div>
</form>
@endsection
