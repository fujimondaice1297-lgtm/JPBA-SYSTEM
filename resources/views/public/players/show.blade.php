@extends('public.layout')

@section('title', ($view['name'] ?? '選手プロフィール') . '｜選手データ｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '選手データ')

@push('styles')
<style>
  .jpba-profile-head {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
  }

  .jpba-profile-photo {
    width: 150px;
    aspect-ratio: 1;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    object-fit: cover;
    background: var(--jpba-soft);
  }

  .jpba-profile-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7788;
    font-weight: 700;
  }

  .jpba-profile-name {
    margin: 0 0 4px;
    color: var(--jpba-blue);
    font-size: 1.35rem;
    font-weight: 700;
  }

  .jpba-profile-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    border-top: 1px solid var(--jpba-line);
    border-left: 1px solid var(--jpba-line);
  }

  .jpba-profile-item {
    min-height: 62px;
    border-right: 1px solid var(--jpba-line);
    border-bottom: 1px solid var(--jpba-line);
    padding: 8px 10px;
  }

  .jpba-profile-label {
    color: #657386;
    font-size: .78rem;
    font-weight: 700;
  }

  .jpba-profile-value {
    margin-top: 2px;
    font-weight: 700;
    overflow-wrap: anywhere;
  }

  .jpba-profile-list {
    margin: 0;
    padding-left: 1.2rem;
  }

  .jpba-profile-subtitle {
    margin: 14px 0 8px;
    color: var(--jpba-blue);
    font-size: .95rem;
    font-weight: 700;
  }

  .jpba-profile-link-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .jpba-profile-badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
  }

  .jpba-profile-badge {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 4px 10px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: var(--jpba-soft);
    font-size: .86rem;
    font-weight: 700;
  }
  @media (max-width: 760px) {
    .jpba-profile-head { grid-template-columns: 1fr; }
    .jpba-profile-grid { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
@php
  $profileItems = [
      ['label' => 'ライセンスNo.', 'value' => $view['license_no'] ?: '-'],
      ['label' => '性別', 'value' => $view['sex'] ?? '-'],
      ['label' => '期別', 'value' => $view['kibetsu'] ?? '-'],
      ['label' => '地区', 'value' => $view['district'] ?? '-'],
      ['label' => 'プロ入り', 'value' => $view['pro_entry_year'] ?? '-'],
      ['label' => '生年月日', 'value' => $view['birth_public'] ?? '-'],
      ['label' => '出身地', 'value' => $view['birthplace'] ?? '-'],
      ['label' => '身長', 'value' => $view['height'] ?? '-'],
      ['label' => '血液型', 'value' => $view['blood'] ?? '-'],
      ['label' => '利き腕', 'value' => $view['dominant_arm'] ?? '-'],
      ['label' => 'A級番号', 'value' => $view['a_license_number'] ?? '-'],
      ['label' => '所属先', 'value' => $view['organization']['name'] ?? '-'],
  ];

  $profileTexts = [
      '趣味・特技' => $view['hobby'] ?? null,
      'ボウリング歴' => $view['bowling_history'] ?? null,
      '今シーズン目標' => $view['season_goal'] ?? null,
      '師匠・コーチ' => $view['coach'] ?? null,
      '用品契約' => $view['equipment_contract'] ?? null,
      '座右の銘' => $view['motto'] ?? null,
      'セールスポイント' => $view['selling_point'] ?? null,
      '自由入力' => $view['free_comment'] ?? null,
  ];

  $formatOfficialStat = function ($label, $value) {
      if ($value === null || $value === '') {
          return '-';
      }
      if ($label === '総賞金額') {
          return '¥' . number_format((int) $value);
      }
      if ($label === '通算アベレージ') {
          return number_format((float) $value, 2);
      }

      return number_format((int) $value);
  };
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="jpba-page-title mb-0">選手プロフィール</h1>
  <a class="jpba-small-button" href="{{ route('public.players.index', request()->query()) }}">検索結果へ戻る</a>
</div>

<section class="jpba-panel" aria-labelledby="profile-heading">
  <div class="jpba-profile-head">
    <div>
      @if(!empty($view['portrait']))
        <img class="jpba-profile-photo" src="{{ $view['portrait'] }}" alt="{{ $view['name'] ?? '選手写真' }}">
      @else
        <div class="jpba-profile-photo jpba-profile-photo-empty">No Photo</div>
      @endif
    </div>

    <div>
      <h2 id="profile-heading" class="jpba-profile-name">{{ $view['name'] ?? '-' }}</h2>
      @if(!empty($view['kana']))
        <div class="text-muted mb-3">{{ $view['kana'] }}</div>
      @endif

      <div class="jpba-profile-grid">
        @foreach($profileItems as $item)
          <div class="jpba-profile-item">
            <div class="jpba-profile-label">{{ $item['label'] }}</div>
            <div class="jpba-profile-value">{{ $item['value'] ?: '-' }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

<section class="jpba-panel" aria-labelledby="official-stats-heading">
  <h2 id="official-stats-heading" class="jpba-section-title">公式戦記録</h2>

  <div class="jpba-profile-grid">
    @foreach(($view['official_stats'] ?? []) as $label => $value)
      <div class="jpba-profile-item">
        <div class="jpba-profile-label">{{ $label }}</div>
        <div class="jpba-profile-value">{{ $formatOfficialStat($label, $value) }}</div>
      </div>
    @endforeach
    @foreach(($view['award_counts'] ?? []) as $label => $value)
      <div class="jpba-profile-item">
        <div class="jpba-profile-label">{{ $label }}</div>
        <div class="jpba-profile-value">{{ number_format((int) $value) }}</div>
      </div>
    @endforeach
  </div>
</section>

<section class="jpba-panel" aria-labelledby="profile-text-heading">
  <h2 id="profile-text-heading" class="jpba-section-title">プロフィール</h2>

  <table class="jpba-data-table">
    <tbody>
      @foreach($profileTexts as $label => $value)
        <tr>
          <th>{{ $label }}</th>
          <td>{{ $value ?: '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</section>

<section class="jpba-panel" aria-labelledby="title-heading">
  <h2 id="title-heading" class="jpba-section-title">タイトル</h2>

  <div class="jpba-profile-badge-row">
    <span class="jpba-profile-badge" data-title-count="official">公式タイトル：{{ number_format((int) ($view['official_titles_count'] ?? 0)) }}</span>
    @unless($view['is_female'] ?? false)
      <span class="jpba-profile-badge" data-title-count="season-trial">シーズントライアル優勝：{{ number_format((int) ($view['season_trial_titles_count'] ?? 0)) }}</span>
    @endunless
  </div>

  <h3 class="jpba-profile-subtitle">公式タイトル</h3>
  @if(($view['titles'] ?? collect())->count())
    <ul class="jpba-profile-list" data-title-list="official">
      @foreach($view['titles'] as $title)
        <li data-title-item="official">
          {{ $title->year }}年 / {{ $title->title_name }}
          @if($title->won_date)
            （{{ \Carbon\Carbon::parse($title->won_date)->format('Y/m/d') }}）
          @endif
        </li>
      @endforeach
    </ul>
  @else
    <p class="mb-0 text-muted">公式タイトルは登録されていません。</p>
  @endif

  @unless($view['is_female'] ?? false)
    <div data-title-section="season-trial">
      <h3 class="jpba-profile-subtitle">シーズントライアル優勝履歴</h3>
      @if(($view['season_trial_titles'] ?? collect())->count())
        <ul class="jpba-profile-list" data-title-list="season-trial">
          @foreach($view['season_trial_titles'] as $title)
            <li data-title-item="season-trial">
              {{ $title->year }}年 / {{ $title->title_name }}
              @if($title->won_date)
                （{{ \Carbon\Carbon::parse($title->won_date)->format('Y/m/d') }}）
              @endif
            </li>
          @endforeach
        </ul>
      @else
        <p class="mb-0 text-muted">確認済みのシーズントライアル優勝履歴は登録されていません。</p>
      @endif
    </div>
  @endunless
</section>

@if(collect($view['sns'] ?? [])->filter()->isNotEmpty() || !empty($view['organization']['url']))
  <section class="jpba-panel" aria-labelledby="link-heading">
    <h2 id="link-heading" class="jpba-section-title">リンク</h2>

    <div class="jpba-profile-link-row">
      @if(!empty($view['organization']['url']))
        <a class="jpba-small-button" href="{{ $view['organization']['url'] }}" target="_blank" rel="noopener">所属先</a>
      @endif

      @foreach(($view['sns'] ?? []) as $label => $url)
        @if($url)
          <a class="jpba-small-button" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
        @endif
      @endforeach
    </div>
  </section>
@endif
@endsection
