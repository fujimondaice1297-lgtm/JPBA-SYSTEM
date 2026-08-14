@extends('public.layout')

@section('title', $managedPage->title . '｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', $managedPage->title)

@push('styles')
<style>
  .jpba-managed-body { overflow-wrap:anywhere; }
  .jpba-managed-body h2 { margin:1.6rem 0 .7rem; padding-bottom:.45rem; border-bottom:2px solid var(--jpba-line); color:var(--jpba-blue); font-size:1.08rem; font-weight:700; }
  .jpba-managed-body h3 { margin:1.25rem 0 .55rem; color:var(--jpba-blue); font-size:1rem; font-weight:700; }
  .jpba-managed-body table { width:100%; border-collapse:collapse; margin:.8rem 0; }
  .jpba-managed-body th,.jpba-managed-body td { padding:.65rem .75rem; border:1px solid var(--jpba-line); vertical-align:top; }
  .jpba-managed-body th { width:190px; background:var(--jpba-soft); }
  .jpba-managed-body a { overflow-wrap:anywhere; }
  @media(max-width:560px){.jpba-managed-body th,.jpba-managed-body td{display:block;width:100%;}}
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">{{ $managedPage->title }}</h1>
<section class="jpba-panel">
  <div class="jpba-managed-body">{!! $managedPage->body_html !!}</div>
</section>
@endsection
