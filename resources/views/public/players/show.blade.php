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

  .jpba-record-table-wrap {
    overflow-x: auto;
  }

  .jpba-record-table {
    min-width: 760px;
    margin-bottom: 0;
  }

  .jpba-record-table th,
  .jpba-record-table td {
    white-space: nowrap;
    text-align: right;
  }

  .jpba-record-table th:first-child,
  .jpba-record-table td:first-child,
  .jpba-tournament-history-table th:nth-child(2),
  .jpba-tournament-history-table td:nth-child(2) {
    text-align: left;
  }

  .jpba-annual-record-extra[hidden],
  .jpba-tournament-history-extra[hidden],
  .jpba-profile-detail-panel[hidden] {
    display: none;
  }

  .jpba-history-details {
    border-top: 1px solid var(--jpba-line);
  }

  .jpba-history-details:last-child {
    border-bottom: 1px solid var(--jpba-line);
  }

  .jpba-history-details summary {
    cursor: pointer;
    padding: 11px 8px;
    color: var(--jpba-blue);
    font-weight: 700;
  }

  .jpba-history-details[open] summary {
    background: var(--jpba-soft);
  }

  .jpba-history-details-body {
    padding: 0 0 12px;
  }

  @media (max-width: 760px) {
    .jpba-profile-head { grid-template-columns: 1fr; }
    .jpba-profile-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 560px) {
    .jpba-record-table th,
    .jpba-record-table td {
      display: table-cell;
      width: auto;
    }
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

  $achievementDetail = function ($record) {
      if ($record->record_type === 'eight_hundred') {
          $detail = $record->series_label ?: $record->game_numbers;
          if ($record->series_total !== null) {
              $detail = trim(($detail ? $detail . ' / ' : '') . '3G合計 ' . number_format((int) $record->series_total));
          }

          return $detail;
      }

      return collect([$record->game_numbers, $record->frame_number])
          ->filter(fn ($value) => filled($value))
          ->implode(' / ');
  };

  $formatHistoryValue = function ($value, string $type = 'integer') {
      if ($value === null || $value === '') {
          return '-';
      }

      if ($type === 'money') {
          return '¥' . number_format((int) $value);
      }

      if ($type === 'average') {
          return number_format((float) $value, 2);
      }

      if ($type === 'points') {
          $formatted = number_format((float) $value, 2, '.', ',');

          return rtrim(rtrim($formatted, '0'), '.');
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

<section class="jpba-panel" aria-labelledby="annual-records-heading">
  <h2 id="annual-records-heading" class="jpba-section-title">年度別公式戦記録</h2>

  @if(collect($view['annual_records'] ?? [])->isNotEmpty())
    <div class="jpba-record-table-wrap">
      <table class="jpba-data-table jpba-record-table">
        <thead>
          <tr>
            <th>年度</th>
            <th>順位</th>
            <th>ゲーム数</th>
            <th>トータルピン</th>
            <th>ポイント</th>
            <th>アベレージ</th>
            <th>獲得賞金</th>
          </tr>
        </thead>
        <tbody>
          @foreach($view['annual_records'] as $record)
            <tr
              data-annual-record
              @if($loop->index >= 10) class="jpba-annual-record-extra" hidden @endif
            >
              <td>
                {{ $record['season_key'] }}年
                @if(!empty($record['is_live_ranking']) && !empty($record['ranking_as_of_date']))
                  <small class="text-muted">
                    （{{ \Carbon\Carbon::parse($record['ranking_as_of_date'])->format('n/j') }}現在）
                  </small>
                @endif
              </td>
              <td>{{ $record['ranking_rank'] !== null ? number_format((int) $record['ranking_rank']) . '位' : '-' }}</td>
              <td>{{ $formatHistoryValue($record['games']) }}</td>
              <td>{{ $formatHistoryValue($record['total_pin']) }}</td>
              <td>{{ $formatHistoryValue($record['points'], 'points') }}</td>
              <td>{{ $formatHistoryValue($record['average'], 'average') }}</td>
              <td>{{ $formatHistoryValue($record['prize_money'], 'money') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if(collect($view['annual_records'])->count() > 10)
      <div class="text-center mt-3">
        <button
          type="button"
          class="jpba-small-button"
          data-annual-record-toggle
          aria-expanded="false"
        >もっと見る</button>
      </div>
    @endif
  @else
    <p class="mb-0 text-muted">年度別公式戦記録は準備中です。</p>
  @endif
</section>

<section class="jpba-panel" aria-labelledby="tournament-history-heading">
  <h2 id="tournament-history-heading" class="jpba-section-title">出場大会</h2>

  @php
    $tournamentHistoryByYear = collect($view['tournament_history_by_year'] ?? []);
  @endphp

  @if($tournamentHistoryByYear->isNotEmpty())
    <div data-tournament-history>
      @foreach($tournamentHistoryByYear as $year => $records)
        <details
          class="jpba-history-details{{ $loop->index >= 5 ? ' jpba-tournament-history-extra' : '' }}"
          data-tournament-history-year="{{ $year }}"
          @if($loop->index >= 5) hidden @endif
        >
          <summary>{{ $year }}年度（{{ number_format(collect($records)->count()) }}大会）</summary>
          <div class="jpba-history-details-body">
            <div class="jpba-record-table-wrap">
              <table class="jpba-data-table jpba-record-table jpba-tournament-history-table">
                <thead>
                  <tr>
                    <th>開催年月日</th>
                    <th>大会名</th>
                    <th>最終順位</th>
                    <th>アベレージ</th>
                    <th>獲得賞金</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($records as $record)
                    <tr>
                      <td>{{ \Carbon\Carbon::parse($record['held_on'])->format('Y/m/d') }}</td>
                      <td>{{ $record['tournament_name'] }}</td>
                      <td>{{ $record['ranking_rank'] !== null ? number_format((int) $record['ranking_rank']) . '位' : '-' }}</td>
                      <td>{{ $formatHistoryValue($record['average'], 'average') }}</td>
                      <td>{{ $formatHistoryValue($record['prize_money'], 'money') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </details>
      @endforeach
    </div>

    @if($tournamentHistoryByYear->count() > 5)
      <div class="text-center mt-3">
        <button
          type="button"
          class="jpba-small-button"
          data-tournament-history-toggle
          aria-expanded="false"
        >もっと見る</button>
      </div>
    @endif
  @else
    <p class="mb-0 text-muted">出場大会履歴は準備中です。</p>
  @endif
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

  <div class="text-center mt-3">
    <button
      type="button"
      class="jpba-small-button"
      data-profile-detail-toggle="titles"
      aria-controls="jpba-title-details"
      aria-expanded="false"
    >詳しく見る</button>
  </div>

  <div
    id="jpba-title-details"
    class="jpba-profile-detail-panel"
    data-profile-detail-panel="titles"
    hidden
  >
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
  </div>
</section>

<section class="jpba-panel" aria-labelledby="achievement-heading" data-achievement-section="all">
  <h2 id="achievement-heading" class="jpba-section-title">褒章</h2>

  <div class="jpba-profile-badge-row">
    @foreach(($view['achievement_sections'] ?? collect()) as $section)
      <span class="jpba-profile-badge" data-achievement-count="{{ $section['type'] }}">
        {{ $section['label'] }}：{{ number_format((int) $section['total_count']) }}
      </span>
    @endforeach
  </div>

  <div class="text-center mt-3">
    <button
      type="button"
      class="jpba-small-button"
      data-profile-detail-toggle="achievements"
      aria-controls="jpba-achievement-details"
      aria-expanded="false"
    >詳しく見る</button>
  </div>

  <div
    id="jpba-achievement-details"
    class="jpba-profile-detail-panel"
    data-profile-detail-panel="achievements"
    hidden
  >
    @foreach(($view['achievement_sections'] ?? collect()) as $section)
      <div data-achievement-section="{{ $section['type'] }}">
        <h3 class="jpba-profile-subtitle">{{ $section['label'] }}</h3>
        @if($section['records']->count())
          <ul class="jpba-profile-list" data-achievement-list="{{ $section['type'] }}">
            @foreach($section['records'] as $record)
              @php
                $awardedOn = $record->awarded_on
                    ? \Carbon\Carbon::parse($record->awarded_on)
                    : null;
                $detail = $achievementDetail($record);
              @endphp
              <li data-achievement-item="{{ $section['type'] }}">
                {{ $awardedOn ? $awardedOn->format('Y') . '年' : '達成年不明' }}
                / {{ $record->tournament_name }}
                @if($detail)
                  / {{ $detail }}
                @endif
                @if($awardedOn)
                  （{{ $awardedOn->format('Y/m/d') }}）
                @endif
              </li>
            @endforeach
          </ul>
        @else
          <p class="mb-0 text-muted">確認済みの達成明細は登録されていません。</p>
        @endif
      </div>
    @endforeach

    @if((int) data_get($view, 'achievement_summary.other.total', 0) > 0)
      <p class="mt-3 mb-1" data-achievement-other-count>
        その他過去達成数：
        パーフェクト {{ number_format((int) data_get($view, 'achievement_summary.other.perfect', 0)) }}件 /
        800シリーズ {{ number_format((int) data_get($view, 'achievement_summary.other.eight_hundred', 0)) }}件 /
        7－10メイド {{ number_format((int) data_get($view, 'achievement_summary.other.seven_ten', 0)) }}件
      </p>
      <p class="mb-0 text-muted">現在確認できる大会分のみデータとして記載</p>
    @endif
  </div>
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

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const annualToggle = document.querySelector('[data-annual-record-toggle]');
    if (annualToggle) {
      const extraRows = Array.from(document.querySelectorAll('.jpba-annual-record-extra'));
      annualToggle.addEventListener('click', function () {
        const expanded = annualToggle.getAttribute('aria-expanded') === 'true';
        extraRows.forEach(function (row) {
          row.hidden = expanded;
        });
        annualToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        annualToggle.textContent = expanded ? 'もっと見る' : '閉じる';
      });
    }

    const tournamentToggle = document.querySelector('[data-tournament-history-toggle]');
    if (tournamentToggle) {
      const extraYears = Array.from(document.querySelectorAll('.jpba-tournament-history-extra'));
      tournamentToggle.addEventListener('click', function () {
        const expanded = tournamentToggle.getAttribute('aria-expanded') === 'true';
        extraYears.forEach(function (year) {
          year.hidden = expanded;
          if (expanded) {
            year.open = false;
          }
        });
        tournamentToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        tournamentToggle.textContent = expanded ? 'もっと見る' : '閉じる';
      });
    }

    document.querySelectorAll('[data-profile-detail-toggle]').forEach(function (toggle) {
      const panelId = toggle.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;
      if (!panel) {
        return;
      }

      toggle.addEventListener('click', function () {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        panel.hidden = expanded;
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        toggle.textContent = expanded ? '詳しく見る' : '閉じる';
      });
    });
  });
</script>
@endpush
