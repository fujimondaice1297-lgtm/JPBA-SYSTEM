@extends('layouts.app')
@section('title','殿堂レコード作成')

@section('content')
<div class="container py-3">
  <h1 class="h5 mb-3">殿堂レコード作成</h1>

  @if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">
      @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul></div>
  @endif

  <form method="post" action="{{ route('hof.store') }}" class="card p-3"><!-- ← 修正 -->
    @csrf
    <div class="mb-3">
      <label class="form-label">ライセンス番号（slug相当）</label>
      <input type="text" name="slug" value="{{ old('slug') }}" class="form-control" placeholder="例: 12345">
      <div class="form-text">.env の JPBA_PROFILES_SLUG_COL（今は license_no）で検索します。</div>
    </div>
    <div class="mb-3">
      <label class="form-label">殿堂入り年（西暦）</label>
      <input type="number" name="year" value="{{ old('year') }}" class="form-control" min="1900" max="2100">
    </div>
    <div class="mb-3">
      <label class="form-label">顕彰文（任意）</label>
      <textarea name="citation" rows="4" class="form-control" placeholder="功績の要約など">{{ old('citation') }}</textarea>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('hof.index') }}">一覧へ戻る</a>
      <button class="btn btn-primary">作成して写真追加へ</button>
    </div>
  </form>
</div>
@endsection
