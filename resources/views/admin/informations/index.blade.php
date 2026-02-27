@extends('layouts.app')

@section('content')
<div class="container" style="max-width:1000px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">お知らせ 管理</h2>
    <a class="btn btn-success" href="{{ route('admin.informations.create') }}">新規作成</a>
  </div>

  <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <select name="year" class="form-select" style="max-width: 180px;">
      <option value="">すべての年度</option>
      @foreach($availableYears as $y)
        <option value="{{ $y }}" {{ (string)$y === (string)request('year','') ? 'selected' : '' }}>{{ $y }}年</option>
      @endforeach
    </select>

    <select name="category" class="form-select" style="max-width: 220px;">
      <option value="">全カテゴリ</option>
      @foreach($categories as $c)
        <option value="{{ $c }}" {{ (string)$c === (string)request('category','') ? 'selected' : '' }}>{{ $c }}</option>
      @endforeach
    </select>

    <select name="audience" class="form-select" style="max-width: 260px;">
      <option value="">全公開対象</option>
      @foreach($audiences as $a)
        <option value="{{ $a }}" {{ (string)$a === (string)request('audience','') ? 'selected' : '' }}>{{ $a }}</option>
      @endforeach
    </select>

    <button class="btn btn-primary">絞り込み</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.informations.index') }}">リセット</a>
  </form>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th style="width:90px">ID</th>
          <th>タイトル</th>
          <th style="width:140px">カテゴリ</th>
          <th style="width:180px">対象</th>
          <th style="width:90px">公開</th>
          <th style="width:180px">更新</th>
          <th style="width:120px"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($infos as $info)
          <tr>
            <td>{{ $info->id }}</td>
            <td class="fw-semibold">{{ $info->title }}</td>
            <td>{{ $info->category ?: '-' }}</td>
            <td>{{ $info->audience }}</td>
            <td>{{ $info->is_public ? 'ON' : 'OFF' }}</td>
            <td>{{ optional($info->updated_at)->format('Y-m-d H:i') }}</td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.informations.edit', $info) }}">編集</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted">データがありません</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $infos->withQueryString()->links('pagination::bootstrap-5') }}
</div>
@endsection