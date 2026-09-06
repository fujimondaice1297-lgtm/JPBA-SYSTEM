@extends('public.layout')

@section('title', $archive->title . '｜大会アーカイブ｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '大会アーカイブ')

@push('styles')
<style>
  .archive-body p{margin:0 0 .5rem}.archive-files{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.archive-file{padding:9px 10px;border:1px solid var(--jpba-line);border-radius:4px;background:var(--jpba-soft);text-decoration:none;font-weight:700;overflow-wrap:anywhere}
  .archive-images{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.archive-images img{display:block;width:100%;height:180px;object-fit:contain;border:1px solid var(--jpba-line);background:#fff}
  @media(max-width:700px){.archive-files,.archive-images{grid-template-columns:1fr}.archive-images img{height:auto}}
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between gap-2 align-items-start flex-wrap">
  <h1 class="jpba-page-title flex-grow-1">{{ $archive->title }}</h1>
  <a class="jpba-small-button" href="{{ route('public.tournament_archives.index', ['year' => $archive->year]) }}">{{ $archive->year }}年一覧へ</a>
</div>
<section class="jpba-panel"><table class="jpba-data-table"><tr><th>開催年度</th><td>{{ $archive->year }}年</td></tr><tr><th>開催日</th><td>{{ $archive->start_on?->format('Y年n月j日') ?: '-' }}@if($archive->end_on) ～ {{ $archive->end_on->format('Y年n月j日') }}@endif</td></tr></table></section>

@php($files = collect($archive->assets ?: [])->where('type', '!=', 'image'))
@if($files->isNotEmpty())
<section class="jpba-panel"><h2 class="jpba-section-title">大会資料・成績</h2><div class="archive-files">@foreach($files as $file)<a class="archive-file" href="{{ asset($file['path']) }}" target="_blank" rel="noopener">{{ $file['title'] ?: '大会資料' }}</a>@endforeach</div></section>
@endif

<section class="jpba-panel"><h2 class="jpba-section-title">大会情報</h2><div class="archive-body">{!! $archive->body_html !!}</div></section>

@php($images = collect($archive->assets ?: [])->where('type', 'image'))
@if($images->isNotEmpty())
<section class="jpba-panel"><h2 class="jpba-section-title">大会写真</h2><div class="archive-images">@foreach($images as $image)<a href="{{ asset($image['path']) }}" target="_blank" rel="noopener"><img src="{{ asset($image['path']) }}" alt="{{ $image['title'] ?: $archive->title }}"></a>@endforeach</div></section>
@endif
@endsection
