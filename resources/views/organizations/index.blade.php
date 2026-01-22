@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">組織マスタ</h1>
  <a href="{{ route('organizations.create') }}" class="btn btn-success">新規登録</a>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" class="row g-2 mb-3">
  <div class="col-auto">
    <input type="text" name="keyword" class="form-control" value="{{ request('keyword') }}" placeholder="名称で検索">
  </div>
  <div class="col-auto">
    <button class="btn btn-primary">検索</button>
    <a href="{{ route('organizations.index') }}" class="btn btn-warning">リセット</a>
  </div>
</form>

<table class="table table-striped align-middle">
  <thead><tr><th>ID</th><th>名称</th><th>URL</th><th style="width:140px;">操作</th></tr></thead>
  <tbody>
    @forelse($items as $v)
      <tr>
        <td>{{ $v->id }}</td>
        <td>{{ $v->name }}</td>
        <td>
          @if($v->url)
            <a href="{{ $v->url }}" target="_blank" rel="noopener">サイト</a>
          @else
            <span class="text-muted">—</span>
          @endif
        </td>
        <td class="text-nowrap">
          <a href="{{ route('organizations.edit',$v->id) }}" class="btn btn-sm btn-primary">編集</a>
          <form method="POST" action="{{ route('organizations.destroy',$v->id) }}" class="d-inline"
            onsubmit="return confirm('削除します。よろしいですか？');">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">削除</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="4" class="text-center text-muted">未登録です。</td></tr>
    @endforelse
  </tbody>
</table>

{{ $items->withQueryString()->links() }}
@endsection
