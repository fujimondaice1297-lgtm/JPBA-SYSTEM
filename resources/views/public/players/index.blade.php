@extends('public.layout')

@section('title', '選手データ｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '選手データ')

@push('styles')
<style>
  .jpba-player-form {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 12px;
  }

  .jpba-player-form label {
    display: block;
    margin-bottom: 4px;
    font-weight: 700;
  }

  .jpba-player-form input,
  .jpba-player-form select {
    width: 100%;
    min-height: 36px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    padding: 6px 8px;
  }

  .jpba-player-form .span-2 { grid-column: span 2; }
  .jpba-player-form .span-3 { grid-column: span 3; }
  .jpba-player-form .span-4 { grid-column: span 4; }
  .jpba-player-form .span-6 { grid-column: span 6; }
  .jpba-player-form .span-12 { grid-column: span 12; }

  .jpba-form-note {
    margin-top: 4px;
    color: #657386;
    font-size: .78rem;
  }

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

  .jpba-player-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--jpba-line);
  }

  .jpba-player-table th,
  .jpba-player-table td {
    border: 1px solid var(--jpba-line);
    padding: 8px 9px;
    vertical-align: top;
  }

  .jpba-player-table th {
    background: var(--jpba-soft);
    color: #35465a;
    font-weight: 700;
    white-space: nowrap;
  }

  .jpba-player-table .license {
    width: 120px;
    font-weight: 700;
  }

  .jpba-player-table .name {
    font-weight: 700;
  }

  .jpba-result-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .jpba-pagination {
    margin-top: 14px;
  }

  @media (max-width: 760px) {
    .jpba-player-form { grid-template-columns: 1fr; }
    .jpba-player-form .span-2,
    .jpba-player-form .span-3,
    .jpba-player-form .span-4,
    .jpba-player-form .span-6,
    .jpba-player-form .span-12 { grid-column: auto; }

    .jpba-player-table,
    .jpba-player-table tbody,
    .jpba-player-table tr,
    .jpba-player-table th,
    .jpba-player-table td {
      display: block;
      width: 100%;
    }

    .jpba-player-table thead { display: none; }
    .jpba-player-table tr { border-bottom: 1px solid var(--jpba-line); }
  }
</style>
@endpush

@section('content')
@php
  $licenseDisplay = function ($bowler) {
      if (($bowler->license_no_num ?? null) !== null && $bowler->license_no_num !== '') {
          return str_pad((string) ((int) $bowler->license_no_num), 4, '0', STR_PAD_LEFT);
      }

      if (preg_match('/(\d{1,4})$/', (string) ($bowler->license_no ?? ''), $matches)) {
          return str_pad($matches[1], 4, '0', STR_PAD_LEFT);
      }

      return $bowler->license_no ?: '-';
  };
@endphp

<h1 class="jpba-page-title">選手データ</h1>

<section class="jpba-panel" aria-labelledby="player-search-heading">
  <h2 id="player-search-heading" class="jpba-section-title">選手データ検索</h2>

  <p class="mb-3">選手データを検索することができます。任意の項目に入力してください。</p>

  <form method="GET" action="{{ route('public.players.index') }}" class="jpba-player-form">
    <div class="span-4">
      <label for="name">選手名</label>
      <input id="name" type="text" name="name" value="{{ $filters['name'] ?? '' }}">
      <div class="jpba-form-note">漢字・全角カタカナ、姓や名の一部でも検索できます。</div>
    </div>

    <div class="span-2">
      <label for="license_from">ライセンス No.</label>
      <input id="license_from" type="text" name="license_from" inputmode="numeric" value="{{ $filters['license_from'] ?? '' }}">
    </div>

    <div class="span-2">
      <label for="license_to">〜</label>
      <input id="license_to" type="text" name="license_to" inputmode="numeric" value="{{ $filters['license_to'] ?? '' }}">
    </div>

    <div class="span-2">
      <label for="gender">性別</label>
      <select id="gender" name="gender">
        <option value="">すべて</option>
        <option value="男性" @selected(($filters['gender'] ?? '') === '男性')>男性</option>
        <option value="女性" @selected(($filters['gender'] ?? '') === '女性')>女性</option>
      </select>
    </div>

    <div class="span-2">
      <label for="player_status">検索区分</label>
      <select id="player_status" name="player_status">
        @foreach(($playerStatusOptions ?? ['active' => '現役選手', 'overseas' => '海外プロ', 'retired' => '退会者']) as $value => $label)
          <option value="{{ $value }}" @selected(($filters['player_status'] ?? 'active') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="span-2">
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

    <div class="span-12 jpba-form-note">
      ライセンスNo.は半角数字で入力してください。アルファベットが含まれる場合は、片方の入力欄に入力して検索してください。
    </div>

    <div class="span-12 jpba-action-row">
      <button type="submit" class="jpba-search-button">検索する</button>
      <a class="jpba-outline-button" href="{{ route('public.players.index') }}">通常検索</a>
      <a class="jpba-outline-button" href="{{ route('public.players.index', ['player_status' => 'overseas']) }}">海外プロ</a>
      <a class="jpba-outline-button" href="{{ route('public.players.index', ['player_status' => 'retired']) }}">退会者</a>
    </div>
  </form>
</section>

<section class="jpba-panel" aria-labelledby="player-result-heading">
  <div class="jpba-result-meta">
    <h2 id="player-result-heading" class="jpba-section-title mb-0">
      {{ ($playerStatusOptions[$filters['player_status'] ?? 'active'] ?? '現役選手') }}検索結果
    </h2>
    <div class="text-muted">該当件数: {{ number_format($bowlers->total()) }}件</div>
  </div>

  @if($bowlers->count())
    <table class="jpba-player-table">
      <thead>
        <tr>
          <th>ライセンスNo.</th>
          <th>氏名</th>
          <th>カナ</th>
          <th>性別</th>
          <th>地区</th>
          <th>期別</th>
          <th>会員種別</th>
        </tr>
      </thead>
      <tbody>
        @foreach($bowlers as $bowler)
          <tr>
            <td class="license">{{ $licenseDisplay($bowler) }}</td>
            <td class="name">
              <a href="{{ route('public.players.show', array_merge(['id' => $bowler->id], request()->query())) }}">
                {{ $bowler->name_kanji ?: '-' }}
              </a>
            </td>
            <td>{{ $bowler->name_kana ?: '-' }}</td>
            <td>{{ $bowler->gender }}</td>
            <td>{{ $bowler->district?->label ?: '-' }}</td>
            <td>{{ $bowler->kibetsu ? $bowler->kibetsu . '期' : '-' }}</td>
            <td>{{ $bowler->membership_type ?: '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="jpba-pagination">
      {{ $bowlers->links() }}
    </div>
  @else
    <p class="mb-0 text-muted">条件に該当する選手データはありません。</p>
  @endif
</section>
@endsection
