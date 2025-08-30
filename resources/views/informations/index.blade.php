@extends('layouts.app')

@section('content')
<div class="container" style="max-width:860px">
  <h2 class="mb-3">お知らせ</h2>

  {{-- 年度フィルタ + 戻る --}}
  <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <select name="year" class="form-select" style="max-width: 180px;">
      <option value="">すべての年度</option>
      @foreach($availableYears as $y)
        <option value="{{ $y }}" {{ (string)$y === (string)request('year', now()->year) ? 'selected' : '' }}>
          {{ $y }}年
        </option>
      @endforeach
    </select>
    <button class="btn btn-primary">表示</button>
    <a href="{{ route('athlete.index') }}" class="btn btn-outline-secondary">インデックスへ戻る</a>
  </form>

  @forelse ($infos as $info)
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title mb-2">{{ $info->title }}</h5>
        <div class="text-muted small mb-2">
          @if($info->starts_at) 公開: {{ $info->starts_at->format('Y-m-d H:i') }} @endif
          @if($info->ends_at) / 終了: {{ $info->ends_at->format('Y-m-d H:i') }} @endif
          <span class="badge text-bg-secondary ms-2">一般公開</span>
          <span class="badge text-bg-light ms-2">更新: {{ $info->updated_at->format('Y-m-d') }}</span>
        </div>
        <div class="card-text" style="white-space:pre-wrap">{{ $info->body }}</div>
      </div>
    </div>
  @empty
    <div class="alert alert-info">現在、一般公開のお知らせはありません。</div>
  @endforelse

  {{ $infos->withQueryString()->onEachSide(1)->links('pagination::bootstrap-5') }}
</div>
@endsection
