@extends('layouts.app')

@section('content')
<div class="container" style="max-width:860px">
  <h2 class="mb-3">お知らせ</h2>

  {{-- 年度フィルタ + カテゴリ + 戻る --}}
  <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <select name="year" class="form-select" style="max-width: 180px;">
      <option value="">すべての年度</option>
      @foreach($availableYears as $y)
        <option value="{{ $y }}" {{ (string)$y === (string)request('year', now()->year) ? 'selected' : '' }}>
          {{ $y }}年
        </option>
      @endforeach
    </select>

    <select name="category" class="form-select" style="max-width: 220px;">
      <option value="">全カテゴリ</option>
      @foreach($categories as $c)
        <option value="{{ $c }}" {{ (string)$c === (string)request('category','') ? 'selected' : '' }}>
          {{ $c }}
        </option>
      @endforeach
    </select>

    <button class="btn btn-primary">表示</button>
    <a href="{{ route('athlete.index') }}" class="btn btn-outline-secondary">インデックスへ戻る</a>
  </form>

  @forelse ($infos as $info)
    @php
      $showUrl = route('informations.show', $info->id);
    @endphp

    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title mb-2">
          <a href="{{ $showUrl }}" class="text-decoration-none">{{ $info->title }}</a>
        </h5>

        <div class="text-muted small mb-2 d-flex flex-wrap align-items-center gap-2">
          @if($info->starts_at) <span>公開: {{ $info->starts_at->format('Y-m-d H:i') }}</span> @endif
          @if($info->ends_at) <span>/ 終了: {{ $info->ends_at->format('Y-m-d H:i') }}</span> @endif

          <span class="badge text-bg-secondary">一般公開</span>

          @if(!empty($info->category))
            <span class="badge text-bg-success">{{ $info->category }}</span>
          @endif

          <span class="badge text-bg-light">更新: {{ $info->updated_at->format('Y-m-d') }}</span>

          @if(($info->files_count ?? 0) > 0)
            <span class="badge text-bg-info text-dark">添付: {{ $info->files_count }}</span>
          @endif
        </div>

        <div class="card-text" style="white-space:pre-wrap">{{ $info->body }}</div>

        <div class="mt-3">
          <a href="{{ $showUrl }}" class="btn btn-sm btn-outline-primary">詳細 / 添付を見る</a>
        </div>
      </div>
    </div>
  @empty
    <div class="alert alert-info">現在、一般公開のお知らせはありません。</div>
  @endforelse

  {{ $infos->withQueryString()->onEachSide(1)->links('pagination::bootstrap-5') }}
</div>
@endsection