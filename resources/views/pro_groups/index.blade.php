@extends('layouts.app')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="mb-0">プログループ管理</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('athlete.index') }}" class="btn btn-outline-primary">インデックスへ戻る</a>
      <a href="{{ route('pro_groups.create') }}" class="btn btn-success">新規作成</a>
    </div>
  </div>

  <table class="table table-bordered align-middle">
    <thead>
      <tr>
        <th>名称</th>
        <th>種別</th>
        <th>保持</th>
        <th>メンバー数</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      @forelse($groups as $g)
        <tr>
          <td>{{ $g->name }}</td>
          <td>{{ $g->type_label }}</td>
          <td>{{ $g->retention_label }}@if($g->expires_at)（〜{{ $g->expires_at->format('Y-m-d') }}）@endif</td>
          <td>{{ $g->members_count }}</td>
          <td class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="{{ route('pro_groups.show',$g) }}">詳細</a>
            @if($g->type==='rule')
              <form method="POST" action="{{ route('pro_groups.rebuild',$g) }}" class="d-inline">
                @csrf <button class="btn btn-sm btn-warning">再計算</button>
              </form>
            @endif
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('pro_groups.export_csv',$g) }}">CSV</a>
            @if(auth()->user()?->isAdmin())
              <form method="POST" action="{{ route('admin.pro_groups.destroy',$g) }}" onsubmit="return confirm('本当に削除しますか？');">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">削除</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5">グループがありません。</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
