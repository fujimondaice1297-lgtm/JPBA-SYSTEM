@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">会場一覧</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('venues.create') }}" class="btn btn-success">新規登録</a>
    <a href="{{ route('tournaments.create') }}" class="btn btn-outline-secondary">大会作成へ</a>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('venues.index') }}" class="row g-2 mb-3">
  <div class="col-md-5">
    <input type="text" name="keyword" class="form-control" value="{{ request('keyword') }}" placeholder="会場名・住所で検索">
  </div>
  <div class="col-md-3">
    <select name="status" class="form-select">
      <option value="active" @selected(request('status', 'active') === 'active')>使用中</option>
      <option value="inactive" @selected(request('status') === 'inactive')>使用停止</option>
      <option value="all" @selected(request('status') === 'all')>すべて</option>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-primary">検索</button>
    <a href="{{ route('venues.index') }}" class="btn btn-warning">リセット</a>
  </div>
</form>

<div class="text-muted small mb-2">{{ $venues->count() }}会場</div>

<div class="table-responsive">
<table class="table table-striped align-middle">
  <thead>
    <tr>
      <th style="width:70px;">ID</th>
      <th>会場名</th>
      <th>状態</th>
      <th>住所</th>
      <th>TEL</th>
      <th>FAX</th>
      <th>最終使用年</th>
      <th>公式サイト</th>
      <th style="width:140px;">操作</th>
    </tr>
  </thead>
  <tbody>
    @forelse($venues as $v)
      <tr>
        <td>{{ $v->id }}</td>
        <td>{{ $v->name }}</td>
        <td>
          @if($v->is_active)
            <span class="badge text-bg-success">使用中</span>
          @else
            <span class="badge text-bg-secondary">停止</span>
          @endif
        </td>
        <td>{{ $v->address }}</td>
        <td>{{ $v->tel }}</td>
        <td>{{ $v->fax }}</td>
        <td>{{ $v->last_hosted_year ?: '—' }}</td>
        <td>
          @if($v->website_url)
            <a href="{{ $v->website_url }}" target="_blank" rel="noopener">サイト</a>
          @else
            <span class="text-muted">—</span>
          @endif
        </td>
        <td class="text-nowrap">
          <a href="{{ route('venues.edit', $v->id) }}" class="btn btn-sm btn-primary">編集</a>
          <form method="POST" action="{{ route('venues.destroy', $v->id) }}" class="d-inline"
                onsubmit="return confirm('削除します。よろしいですか？');">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">削除</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="9" class="text-center text-muted">該当する会場がありません。</td></tr>
    @endforelse
  </tbody>
</table>
</div>
@endsection
