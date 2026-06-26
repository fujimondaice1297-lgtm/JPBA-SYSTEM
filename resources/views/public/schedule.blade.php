@extends('public.layout')

@section('title', 'スケジュール｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'スケジュール')

@push('styles')
<style>
  .jpba-year-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
  }

  .jpba-year-nav a {
    display: inline-flex;
    min-height: 32px;
    align-items: center;
    padding: 4px 10px;
    border: 1px solid var(--jpba-line);
    border-radius: 4px;
    background: var(--jpba-soft);
    text-decoration: none;
    font-weight: 700;
  }

  .jpba-year-nav a.active {
    background: var(--jpba-blue);
    color: #fff;
    border-color: var(--jpba-blue);
  }

  .jpba-schedule-month {
    margin-bottom: 18px;
  }

  .jpba-schedule-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--jpba-line);
  }

  .jpba-schedule-table th,
  .jpba-schedule-table td {
    border: 1px solid var(--jpba-line);
    padding: 9px 10px;
    vertical-align: top;
  }

  .jpba-schedule-table th {
    background: var(--jpba-soft);
    color: #35465a;
    font-weight: 700;
    white-space: nowrap;
  }

  .jpba-schedule-type {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 2px 8px;
    border-radius: 4px;
    background: #edf3fb;
    color: var(--jpba-blue);
    font-size: .78rem;
    font-weight: 700;
  }

  .jpba-schedule-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .jpba-schedule-links a {
    display: inline-flex;
    min-height: 26px;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    background: #f1f5f9;
    text-decoration: none;
    font-size: .78rem;
    font-weight: 700;
  }

  @media (max-width: 720px) {
    .jpba-schedule-table,
    .jpba-schedule-table tbody,
    .jpba-schedule-table tr,
    .jpba-schedule-table th,
    .jpba-schedule-table td {
      display: block;
      width: 100%;
    }

    .jpba-schedule-table thead { display: none; }
    .jpba-schedule-table tr { border-bottom: 1px solid var(--jpba-line); }
  }
</style>
@endpush

@section('content')
@php
  $monthNames = [
      1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
      7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月',
  ];
@endphp

<h1 class="jpba-page-title">スケジュール</h1>

@if(!empty($availableYears))
  <nav class="jpba-year-nav" aria-label="年度切替">
    @foreach($availableYears as $availableYear)
      <a class="{{ (int)$availableYear === (int)$year ? 'active' : '' }}"
         href="{{ route('public.schedule', ['year' => $availableYear]) }}">
        {{ $availableYear }}年
      </a>
    @endforeach
  </nav>
@endif

@if($scheduleRows->count())
  @foreach($groupedScheduleRows as $month => $rows)
    <section class="jpba-schedule-month" aria-labelledby="month-{{ $month }}">
      <h2 id="month-{{ $month }}" class="jpba-section-title">{{ $monthNames[(int)$month] ?? '日程未定' }}</h2>

      <table class="jpba-schedule-table">
        <thead>
          <tr>
            <th style="width: 150px;">日程</th>
            <th style="width: 130px;">区分</th>
            <th>大会・予定</th>
            <th style="width: 180px;">会場</th>
            <th style="width: 180px;">資料</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            <tr>
              <td>{{ $row['period'] ?: '-' }}</td>
              <td><span class="jpba-schedule-type">{{ $row['type_label'] }}</span></td>
              <td class="fw-bold">{{ $row['title'] }}</td>
              <td>{{ $row['venue'] ?: '-' }}</td>
              <td>
                @if(!empty($row['links']))
                  <div class="jpba-schedule-links">
                    @foreach($row['links'] as $link)
                      <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
                    @endforeach
                  </div>
                @else
                  -
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
  @endforeach
@else
  <div class="jpba-panel text-muted">
    表示できるスケジュールはまだ登録されていません。
  </div>
@endif
@endsection
