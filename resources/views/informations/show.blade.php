@extends(($mode ?? 'public') === 'public' ? 'public.layout' : 'layouts.app')

@section('title', (($information->title ?? 'お知らせ 詳細').'｜公益社団法人 日本プロボウリング協会'))
@section('breadcrumb', 'INFORMATION')

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

        @if(!empty($information->category))
          <span class="badge text-bg-success">{{ $information->category }}</span>
        @endif

        <span class="badge text-bg-light">更新: {{ optional($information->updated_at)->format('Y-m-d') }}</span>
      </div>

      @if(($information->body_format ?? 'plain') === 'html')
        <div class="card-text jpba-information-body">{!! $information->body !!}</div>
      @else
        <div class="card-text" style="white-space:pre-wrap">{{ $information->body }}</div>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      添付ファイル
    </div>
    <div class="card-body">
      @if(isset($files) && count($files) > 0)
        @php
          $images = $files->filter(fn ($file) => str_contains(strtolower((string) $file->type), 'image'));
          $documents = $files->reject(fn ($file) => str_contains(strtolower((string) $file->type), 'image'));
        @endphp

        @if($images->isNotEmpty())
          <div class="row g-3 mb-3">
            @foreach($images as $file)
              @if($file->publicUrl())
                <div class="col-6 col-md-4">
                  <a href="{{ $file->publicUrl() }}" target="_blank" rel="noopener" class="d-block text-decoration-none">
                    <img src="{{ $file->publicUrl() }}" alt="{{ $file->title ?: $information->title }}" class="img-fluid rounded border" loading="lazy">
                    @if($file->title)<div class="small mt-1">{{ $file->title }}</div>@endif
                  </a>
                </div>
              @endif
            @endforeach
          </div>
        @endif

        @if($documents->isNotEmpty())
        <div class="list-group">
          @foreach($documents as $file)
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
        @endif
      @else
        <div class="text-muted">添付ファイルはありません。</div>
      @endif
    </div>
  </div>
</div>
@endsection
