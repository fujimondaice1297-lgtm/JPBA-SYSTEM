@extends('public.layout')

@section('title', $history['year'].'年度プロテスト過去結果｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'プロテスト 過去結果')

@push('styles')
<style>
  .protest-archive-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    color: #4f5b67;
  }

  .protest-archive-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .protest-archive-card {
    padding: 15px;
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    background: #fff;
  }

  .protest-archive-card h2 {
    margin: 0 0 8px;
    color: var(--jpba-blue);
    font-size: 1.05rem;
  }

  .protest-archive-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 9px 0;
    border-top: 1px dotted var(--jpba-line);
  }

  .protest-archive-link:first-of-type {
    border-top: 0;
  }

  .protest-pdf-badge {
    flex: 0 0 auto;
    padding: 2px 7px;
    border-radius: 3px;
    background: #c92835;
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
  }

  .protest-cancelled {
    padding: 16px;
    border-left: 4px solid var(--jpba-red);
    background: #fff7f7;
  }

  @media (max-width: 760px) {
    .protest-archive-grid { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">{{ $history['year'] }}年度 プロテスト結果</h1>

<section class="jpba-panel">
  <h2 class="jpba-section-title">実施記録</h2>
  @if($history['held'])
    <div class="protest-archive-summary">
      <span>男子：{{ $history['male_generation'] }}</span>
      <span>女子：{{ $history['female_generation'] }}</span>
      <span>保存資料：{{ count($history['documents']) }}件</span>
    </div>
    <p class="mb-0 mt-2 text-muted">各PDFは、JPBA公式サイトで公開されていた結果資料の保存版です。</p>
  @else
    <div class="protest-cancelled">{{ $history['note'] }}</div>
  @endif
</section>

@if($history['held'])
  @php($documentsByGroup = collect($history['documents'])->groupBy('group'))
  <div class="protest-archive-grid">
    @foreach($groupLabels as $group => $groupLabel)
      @if($documentsByGroup->has($group))
        <section class="protest-archive-card">
          <h2>{{ $groupLabel }}</h2>
          @foreach($documentsByGroup->get($group) as $document)
            <a class="protest-archive-link" href="{{ url($document['url']) }}" target="_blank" rel="noopener">
              <span>{{ $document['label'] }}</span>
              <span class="protest-pdf-badge">PDF</span>
            </a>
          @endforeach
        </section>
      @endif
    @endforeach
  </div>
@endif

<p class="mt-3 mb-0"><a class="jpba-outline-button" href="{{ route('public.protest') }}">プロテスト案内へ戻る</a></p>
@endsection
