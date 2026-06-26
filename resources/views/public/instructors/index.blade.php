@extends('public.layout')

@section('title', 'インストラクター｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'インストラクター')

@push('styles')
<style>
  .jpba-instructor-lead {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 16px;
  }

  .jpba-count-list {
    display: grid;
    gap: 8px;
  }

  .jpba-count-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: var(--jpba-soft);
    padding: 8px 10px;
    font-weight: 700;
  }

  .jpba-feature-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .jpba-feature-link {
    display: block;
    min-height: 100%;
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    background: #fff;
    padding: 12px;
    text-decoration: none;
  }

  .jpba-feature-title {
    color: var(--jpba-blue);
    font-weight: 700;
  }

  .jpba-feature-description {
    margin-top: 4px;
    color: #596675;
    font-size: .86rem;
  }

  .jpba-instructor-form {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 12px;
  }

  .jpba-instructor-form label {
    display: block;
    margin-bottom: 4px;
    font-weight: 700;
  }

  .jpba-instructor-form input,
  .jpba-instructor-form select {
    width: 100%;
    min-height: 36px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    padding: 6px 8px;
  }

  .jpba-instructor-form .span-2 { grid-column: span 2; }
  .jpba-instructor-form .span-3 { grid-column: span 3; }
  .jpba-instructor-form .span-4 { grid-column: span 4; }
  .jpba-instructor-form .span-12 { grid-column: span 12; }

  .jpba-action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .jpba-search-button {
    min-height: 36px;
    border: 1px solid var(--jpba-blue);
    border-radius: 4px;
    background: var(--jpba-blue);
    color: #fff;
    padding: 6px 16px;
    font-weight: 700;
  }

  .jpba-outline-button {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: #fff;
    padding: 6px 12px;
    text-decoration: none;
    font-weight: 700;
  }

  .jpba-instructor-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--jpba-line);
  }

  .jpba-instructor-table th,
  .jpba-instructor-table td {
    border: 1px solid var(--jpba-line);
    padding: 8px 9px;
    vertical-align: top;
  }

  .jpba-instructor-table th {
    background: var(--jpba-soft);
    color: #35465a;
    font-weight: 700;
    white-space: nowrap;
  }

  .jpba-instructor-badge {
    display: inline-flex;
    min-height: 24px;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    background: #edf3fb;
    color: var(--jpba-blue);
    font-size: .78rem;
    font-weight: 700;
  }

  .jpba-pagination { margin-top: 14px; }

  @media (max-width: 820px) {
    .jpba-instructor-lead,
    .jpba-feature-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 760px) {
    .jpba-instructor-form { grid-template-columns: 1fr; }
    .jpba-instructor-form .span-2,
    .jpba-instructor-form .span-3,
    .jpba-instructor-form .span-4,
    .jpba-instructor-form .span-12 { grid-column: auto; }

    .jpba-instructor-table,
    .jpba-instructor-table tbody,
    .jpba-instructor-table tr,
    .jpba-instructor-table th,
    .jpba-instructor-table td {
      display: block;
      width: 100%;
    }

    .jpba-instructor-table thead { display: none; }
    .jpba-instructor-table tr { border-bottom: 1px solid var(--jpba-line); }
  }
</style>
@endpush

@section('content')
@php
  $summary = $instructorConfig['summary'] ?? [];
  $featureLinks = $instructorConfig['feature_links'] ?? [];
  $licenseLinks = $instructorConfig['license_links'] ?? [];
  $displayCode = fn ($instructor) => $instructor->license_no
      ?? $instructor->cert_no
      ?? $instructor->legacy_instructor_license_no
      ?? '-';
  $sexLabel = fn ($instructor) => $instructor->sex === null
      ? '-'
      : ($instructor->sex ? '男性' : '女性');
@endphp

<h1 class="jpba-page-title">インストラクター</h1>

<section class="jpba-panel" aria-labelledby="instructor-overview-heading">
  <div class="jpba-instructor-lead">
    <div>
      <h2 id="instructor-overview-heading" class="jpba-section-title">制度・案内</h2>
      @foreach($summary as $paragraph)
        <p>{{ $paragraph }}</p>
      @endforeach

      @if($instructorInformations->count())
        <h3 class="h6 fw-bold mt-3">最新のお知らせ</h3>
        <ul class="mb-0">
          @foreach($instructorInformations as $information)
            <li>
              <a href="{{ route('informations.show', $information) }}">{{ $information->title }}</a>
              @if($information->published_at)
                <span class="text-muted small">({{ $information->published_at->format('Y/m/d') }})</span>
              @endif
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    <div>
      <h3 class="h6 fw-bold">登録件数</h3>
      <div class="jpba-count-list">
        @foreach($categoryOptions as $value => $label)
          <div class="jpba-count-item">
            <span>{{ $label }}</span>
            <span>{{ number_format((int)($categoryCounts[$value] ?? 0)) }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

<section class="jpba-panel" aria-labelledby="feature-heading">
  <h2 id="feature-heading" class="jpba-section-title">関連情報</h2>

  <div class="jpba-feature-grid">
    @foreach($featureLinks as $link)
      <a class="jpba-feature-link" href="{{ $link['url'] }}" target="_blank" rel="noopener">
        <div class="jpba-feature-title">{{ $link['label'] }}</div>
        <div class="jpba-feature-description">{{ $link['description'] ?? '' }}</div>
      </a>
    @endforeach
  </div>
</section>

<section class="jpba-panel" aria-labelledby="license-heading">
  <h2 id="license-heading" class="jpba-section-title">ライセンス別情報</h2>

  @if(!empty($licenseLinks))
    <div class="jpba-link-grid">
      @foreach($licenseLinks as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
      @endforeach
    </div>
  @else
    <p class="mb-0 text-muted">ライセンス別情報はまだ登録されていません。</p>
  @endif
</section>

<section class="jpba-panel" aria-labelledby="search-heading">
  <h2 id="search-heading" class="jpba-section-title">インストラクター検索</h2>

  <form method="GET" action="{{ route('public.instructors.index') }}" class="jpba-instructor-form">
    <div class="span-3">
      <label for="name">氏名</label>
      <input id="name" type="text" name="name" value="{{ $filters['name'] ?? '' }}">
    </div>

    <div class="span-3">
      <label for="license_no">ライセンスNo.</label>
      <input id="license_no" type="text" name="license_no" value="{{ $filters['license_no'] ?? '' }}">
    </div>

    <div class="span-3">
      <label for="category">区分</label>
      <select id="category" name="category">
        <option value="">すべて</option>
        @foreach($categoryOptions as $value => $label)
          <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="span-3">
      <label for="grade">級</label>
      <select id="grade" name="grade">
        <option value="">すべて</option>
        @foreach($gradeOptions as $grade)
          <option value="{{ $grade }}" @selected(($filters['grade'] ?? '') === $grade)>{{ $grade }}</option>
        @endforeach
      </select>
    </div>

    <div class="span-3">
      <label for="district_id">地区</label>
      <select id="district_id" name="district_id">
        <option value="">すべて</option>
        @foreach($districts as $district)
          <option value="{{ $district->id }}" @selected((string)($filters['district_id'] ?? '') === (string)$district->id)>
            {{ $district->label }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="span-12 jpba-action-row">
      <button type="submit" class="jpba-search-button">検索する</button>
      <a class="jpba-outline-button" href="{{ route('public.instructors.index') }}">リセット</a>
    </div>
  </form>
</section>

<section class="jpba-panel" aria-labelledby="result-heading">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 id="result-heading" class="jpba-section-title mb-0">ライセンス別一覧</h2>
    <div class="text-muted">該当件数: {{ number_format($instructors->total()) }}件</div>
  </div>

  @if($instructors->count())
    <table class="jpba-instructor-table">
      <thead>
        <tr>
          <th>氏名</th>
          <th>番号</th>
          <th>区分</th>
          <th>級</th>
          <th>地区</th>
          <th>性別</th>
          <th>更新状態</th>
        </tr>
      </thead>
      <tbody>
        @foreach($instructors as $instructor)
          <tr>
            <td class="fw-bold">{{ $instructor->name ?: '-' }}</td>
            <td>{{ $displayCode($instructor) }}</td>
            <td><span class="jpba-instructor-badge">{{ $instructor->type_label }}</span></td>
            <td>{{ $instructor->grade ?: '-' }}</td>
            <td>{{ $instructor->district?->label ?: '-' }}</td>
            <td>{{ $sexLabel($instructor) }}</td>
            <td>{{ $instructor->renewal_status_label }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="jpba-pagination">
      {{ $instructors->links() }}
    </div>
  @else
    <p class="mb-0 text-muted">条件に該当するインストラクターはありません。</p>
  @endif
</section>
@endsection
