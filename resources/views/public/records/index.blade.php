@extends('public.layout')
@section('title', 'シード・資格・公認記録｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'シード・資格・公認記録')
@push('styles')
<style>
  .record-hub{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.record-card{display:flex;flex-direction:column;padding:16px;border:1px solid var(--jpba-line);border-top:4px solid var(--jpba-blue);border-radius:6px;background:#fff}.record-card h2{margin:0 0 6px;color:var(--jpba-blue);font-size:1.08rem;font-weight:800}.record-card p{flex:1;margin:0 0 12px;color:#5f6b7a}.record-counts{display:flex;flex-wrap:wrap;gap:8px}.record-count{padding:7px 10px;border-radius:4px;background:var(--jpba-soft);font-weight:700}
  @media(max-width:700px){.record-hub{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
<h1 class="jpba-page-title">シード・資格・公認記録</h1>
<p>トーナメントシード、永久資格、日本プロボウリング殿堂、JPBA公認最高記録をまとめて確認できます。</p>
<div class="record-hub">
  <article class="record-card"><h2>トーナメントシード</h2><p>年度別の男子シード、女子第1・第2シードなどを掲載します。</p><a class="jpba-small-button" href="{{ route('public.records.seed') }}">シード一覧</a></article>
  @if($page=$managedPages->get('permanent-seed'))<article class="record-card"><h2>永久シードプロ</h2><p>永久シード権の条件と取得者を掲載します。</p><a class="jpba-small-button" href="{{ route('public.managed_pages.show',$page) }}">永久シード一覧</a></article>@endif
  <article class="record-card"><h2>男子 永久A級ライセンス</h2><p>取得条件と取得順による保持者一覧です。</p><a class="jpba-small-button" href="{{ route('public.records.a_class.m') }}">男子一覧</a></article>
  <article class="record-card"><h2>女子 永久A級ライセンス</h2><p>取得条件と取得順による保持者一覧です。</p><a class="jpba-small-button" href="{{ route('public.records.a_class.f') }}">女子一覧</a></article>
  @if($page=$managedPages->get('hall-of-fame'))<article class="record-card"><h2>日本プロボウリング殿堂</h2><p>年度別の殿堂表彰者を掲載します。</p><a class="jpba-small-button" href="{{ route('public.managed_pages.show',$page) }}">殿堂入り一覧</a></article>@endif
  @if($page=$managedPages->get('official-high-records'))<article class="record-card"><h2>JPBA公認最高記録</h2><p>男女別のシリーズ最高記録と通算タイトル記録を掲載します。</p><a class="jpba-small-button" href="{{ route('public.managed_pages.show',$page) }}">最高記録一覧</a></article>@endif
</div>
<section class="jpba-panel mt-3"><h2 class="jpba-section-title">公認達成記録の登録状況</h2><div class="record-counts"><span class="record-count">パーフェクト {{ number_format((int)($recordCounts['perfect'] ?? 0)) }}件</span><span class="record-count">800シリーズ {{ number_format((int)($recordCounts['eight_hundred'] ?? 0)) }}件</span><span class="record-count">7-10メイド {{ number_format((int)($recordCounts['seven_ten'] ?? 0)) }}件</span></div><p class="text-muted mt-2 mb-0">達成明細は各選手の一般公開プロフィールで確認できます。</p></section>
@endsection
