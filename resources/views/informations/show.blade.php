@extends('layouts.app')

@section('content')
<div class="container" style="max-width:860px">
  @php
    /** @var \App\Models\Information $information */
    $isMember = ($mode ?? 'public') === 'member';
    $backUrl = $isMember ? route('informations.member') : route('informations.index');
    $downloadRoute = $isMember ? 'information_files.member.download' : 'information_files.download';
    $labelMode = $isMember ? '会員向け' : '一般公開';
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">お知らせ 詳細</h2>
    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">一覧へ戻る</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <h4 class="card-title mb-2">{{ $information->title }}</h4>

      <div class="text-muted small mb-3 d-flex flex-wrap align-items-center gap-2">
        @if($information->starts_at) <span>公開: {{ $information->starts_at->format('Y-m-d H:i') }}</span> @endif
        @if($information->ends_at) <span>/ 終了: {{ $information->ends_at->format('Y-m-d H:i') }}</span> @endif

        <span class="badge text-bg-secondary">{{ $labelMode }}</span>
        <span class="badge text-bg-light">更新: {{ optional($information->updated_at)->format('Y-m-d') }}</span>
      </div>

      <div class="card-text" style="white-space:pre-wrap">{{ $information->body }}</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      添付ファイル
    </div>
    <div class="card-body">
      @if(isset($files) && count($files) > 0)
        <div class="list-group">
          @foreach($files as $file)
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold">
                  {{ $file->title ?: basename((string)$file->file_path) }}
                </div>
                <div class="small text-muted">
                  type: {{ $file->type ?? '-' }}
                  / visibility: {{ $file->visibility ?? '-' }}
                </div>
              </div>
              <div>
                <a class="btn btn-sm btn-primary" href="{{ route($downloadRoute, $file->id) }}">ダウンロード</a>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="text-muted">添付ファイルはありません。</div>
      @endif
    </div>
  </div>
</div>
@endsection
