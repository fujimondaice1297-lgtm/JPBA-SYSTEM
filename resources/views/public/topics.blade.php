@extends('public.layout')

@section('title', 'トピックス｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'トピックス')

@push('styles')
<style>
  .jpba-topic-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 18px;
  }

  .jpba-topic-list {
    display: grid;
    gap: 14px;
  }

  .jpba-topic {
    display: grid;
    grid-template-columns: 160px minmax(0, 1fr);
    gap: 14px;
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    padding: 12px;
    background: #fff;
  }

  .jpba-topic-image {
    aspect-ratio: 4 / 3;
    background: var(--jpba-soft);
    border: 1px solid var(--jpba-line);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7788;
    font-weight: 700;
    overflow: hidden;
  }

  .jpba-topic-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .jpba-topic-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    color: #687789;
    font-size: .84rem;
    margin-bottom: 5px;
  }

  .jpba-topic-title {
    margin: 0 0 8px;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.45;
  }

  .jpba-topic-title a {
    text-decoration: none;
  }

  .jpba-topic-files {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
  }

  .jpba-topic-files a,
  .jpba-filter-link {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 3px 9px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: var(--jpba-soft);
    text-decoration: none;
    font-weight: 700;
  }

  .jpba-filter-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .jpba-filter-link.is-active {
    background: var(--jpba-blue);
    border-color: var(--jpba-blue);
    color: #fff;
  }

  @media (max-width: 900px) {
    .jpba-topic-layout { grid-template-columns: 1fr; }
  }

  @media (max-width: 640px) {
    .jpba-topic { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
@php
  $legacyLinks = $topicsConfig['legacy_links'] ?? [];
  $lead = $topicsConfig['lead'] ?? '';

  $fileUrl = function ($file) {
      return asset('storage/' . ltrim((string) $file->file_path, '/'));
  };

  $isImage = function ($file) {
      $type = strtolower((string) ($file->type ?? ''));
      $path = strtolower((string) ($file->file_path ?? ''));

      return str_contains($type, 'image') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/', $path);
  };
@endphp

<h1 class="jpba-page-title">トピックス</h1>

<section class="jpba-panel" aria-labelledby="topics-overview-heading">
  <h2 id="topics-overview-heading" class="jpba-section-title">公開記事</h2>
  <p class="mb-0">{{ $lead }}</p>
</section>

<div class="jpba-topic-layout">
  <section class="jpba-panel" aria-labelledby="topics-list-heading">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
      <h2 id="topics-list-heading" class="jpba-section-title mb-0">記事一覧</h2>
      <div class="text-muted">該当件数: {{ number_format($topics->total()) }}件</div>
    </div>

    @if($topics->count())
      <div class="jpba-topic-list">
        @foreach($topics as $topic)
          @php
            $files = $topic->files ?? collect();
            $image = $files->first(fn ($file) => $isImage($file));
            $published = $topic->published_at ?: $topic->updated_at ?: $topic->created_at;
          @endphp
          <article class="jpba-topic">
            <div class="jpba-topic-image">
              @if($image)
                <img src="{{ $fileUrl($image) }}" alt="{{ $topic->title }}">
              @else
                TOPICS
              @endif
            </div>
            <div>
              <div class="jpba-topic-meta">
                @if($published)
                  <time datetime="{{ $published->format('Y-m-d') }}">{{ $published->format('Y/m/d') }}</time>
                @endif
                <span>{{ $topic->category ?: 'NEWS' }}</span>
              </div>
              <h3 class="jpba-topic-title">
                <a href="{{ route('informations.show', $topic) }}">{{ $topic->title }}</a>
              </h3>
              @if(!empty($topic->body))
                <p class="mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($topic->body), 180) }}</p>
              @endif

              @if($files->count())
                <div class="jpba-topic-files">
                  @foreach($files->take(4) as $file)
                    <a href="{{ $fileUrl($file) }}" target="_blank" rel="noopener">{{ $file->title ?: '添付' }}</a>
                  @endforeach
                </div>
              @endif
            </div>
          </article>
        @endforeach
      </div>

      <div class="mt-3">
        {{ $topics->links() }}
      </div>
    @else
      <p class="mb-0 text-muted">公開中のトピックス記事はまだ登録されていません。</p>
    @endif
  </section>

  <aside>
    <section class="jpba-panel" aria-labelledby="topics-filter-heading">
      <h2 id="topics-filter-heading" class="jpba-section-title">カテゴリ</h2>
      <div class="jpba-filter-list">
        <a class="jpba-filter-link {{ $category === null ? 'is-active' : '' }}" href="{{ route('public.topics') }}">すべて</a>
        @foreach($categories as $option)
          <a class="jpba-filter-link {{ $category === $option ? 'is-active' : '' }}" href="{{ route('public.topics', ['category' => $option]) }}">
            {{ $option }}
          </a>
        @endforeach
      </div>
    </section>

    @if(!empty($legacyLinks))
      <section class="jpba-panel" aria-labelledby="topics-legacy-heading">
        <h2 id="topics-legacy-heading" class="jpba-section-title">現行サイト導線</h2>
        <div class="jpba-link-grid">
          @foreach($legacyLinks as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
          @endforeach
        </div>
      </section>
    @endif
  </aside>
</div>
@endsection
