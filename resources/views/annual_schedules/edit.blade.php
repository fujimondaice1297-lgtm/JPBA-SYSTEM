@extends('layouts.app')

@section('content')
@php
  $rowIndex = 0;
  $oldRows = old('rows');
  if (is_array($oldRows)) {
      $displayRows = collect($oldRows)->groupBy(fn ($row) => (int) ($row['month'] ?? 0));
  } else {
      $displayRows = $groupedRows;
  }
@endphp
<style>
  .schedule-admin-shell { max-width:1800px; margin:0 auto; }
  .schedule-admin-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:16px; }
  .schedule-admin-head h2 { margin:0 0 5px; font-weight:800; }
  .schedule-admin-actions { display:flex; flex-wrap:wrap; gap:8px; }
  .schedule-status { display:inline-flex; padding:4px 9px; border-radius:999px; font-size:.8rem; font-weight:800; }
  .schedule-status.published { background:#dcfce7; color:#166534; }
  .schedule-status.draft { background:#fef3c7; color:#92400e; }
  .schedule-guide { margin-bottom:16px; padding:13px 15px; border-left:5px solid #2767a8; border-radius:8px; background:#edf5ff; }
  .schedule-header-fields { display:grid; grid-template-columns:2fr 180px 2fr; gap:12px; margin-bottom:14px; }
  .schedule-table-wrap { overflow:auto; max-height:72vh; border:1px solid #9ca8b8; border-radius:8px; background:#fff; }
  .schedule-edit-table { width:100%; min-width:1510px; border-collapse:separate; border-spacing:0; font-size:.76rem; }
  .schedule-edit-table th, .schedule-edit-table td { border-right:1px solid #b6c0cd; border-bottom:1px solid #b6c0cd; padding:4px; vertical-align:middle; }
  .schedule-edit-table thead th { position:sticky; top:0; z-index:4; background:#e6eef7; text-align:center; font-weight:800; }
  .schedule-edit-table .month-cell { position:sticky; left:0; z-index:2; width:70px; background:#eef4fa; text-align:center; color:#174a8b; font-weight:800; }
  .schedule-edit-table input, .schedule-edit-table select, .schedule-edit-table textarea { width:100%; min-height:33px; padding:5px 6px; border:1px solid #c9d2dd; border-radius:4px; background:#fff; font-size:.76rem; }
  .schedule-edit-table textarea { min-height:48px; resize:vertical; }
  .schedule-edit-table .mark-input { width:44px; text-align:center; }
  .schedule-edit-table tr.qualifier-row td { background:#f0f7ff; }
  .schedule-month-actions { display:flex; flex-direction:column; gap:6px; align-items:center; }
  .schedule-linked { display:block; margin-top:3px; color:#087a55; font-size:.68rem; font-weight:700; }
  .schedule-save-bar { position:sticky; bottom:0; z-index:5; display:flex; justify-content:flex-end; gap:9px; padding:12px; border:1px solid #cfd7e2; border-top:0; border-radius:0 0 8px 8px; background:rgba(255,255,255,.96); box-shadow:0 -5px 18px rgba(20,40,70,.08); }
  @media(max-width:900px) { .schedule-header-fields { grid-template-columns:1fr; } }
</style>

<div class="schedule-admin-shell">
  <div class="schedule-admin-head">
    <div>
      <h2>年間予定表の編集</h2>
      <div>{{ $schedule->year }}年　<span class="schedule-status {{ $schedule->status }}">{{ $schedule->status === 'published' ? '一般公開中' : '下書き' }}</span></div>
    </div>
    <div class="schedule-admin-actions">
      <select class="form-select form-select-sm" style="width:115px" aria-label="年度切替" onchange="if(this.value){location.href=this.value}">
        @foreach($availableYears as $availableYear)<option value="{{ route('annual_schedules.edit', $availableYear) }}" {{ (int)$availableYear === (int)$schedule->year ? 'selected' : '' }}>{{ $availableYear }}年</option>@endforeach
      </select>
      @if($schedule->year === 2026)
        <form method="POST" action="{{ route('annual_schedules.import_official', $schedule->year) }}" onsubmit="return confirm('現在の予定表を、公式PDF（2026年7月1日現在）の内容で置き換えます。よろしいですか？')">
          @csrf<input type="hidden" name="replace" value="1"><button class="btn btn-outline-primary btn-sm">公式PDFを再取込</button>
        </form>
      @endif
      <a class="btn btn-outline-danger btn-sm" href="{{ route('annual_schedules.pdf', $schedule->year) }}" target="_blank">PDF確認</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('public.schedule', ['year' => $schedule->year]) }}" target="_blank">一般画面</a>
      @if($schedule->status === 'published')
        <form method="POST" action="{{ route('annual_schedules.unpublish', $schedule->year) }}">@csrf<button class="btn btn-outline-warning btn-sm">非公開にする</button></form>
      @else
        <form method="POST" action="{{ route('annual_schedules.publish', $schedule->year) }}">@csrf<button class="btn btn-success btn-sm">一般公開する</button></form>
      @endif
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>入力内容を確認してください。</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="schedule-guide">
    公式PDFと同じ並びで直接編集できます。大会作成画面で「年間予定表へ反映」を選ぶと、この表へ自動追加されます。同名行がある場合は紐づけ方法を確認します。
  </div>

  <form method="POST" action="{{ route('annual_schedules.update', $schedule->year) }}" id="annual-schedule-form">
    @csrf @method('PUT')
    <div class="schedule-header-fields">
      <div><label class="form-label">表題</label><input class="form-control" name="title" value="{{ old('title', $schedule->title) }}" required></div>
      <div><label class="form-label">「現在」の日付</label><input class="form-control" type="date" name="source_updated_on" value="{{ old('source_updated_on', $schedule->source_updated_on?->toDateString()) }}"></div>
      <div><label class="form-label">参照元URL（管理用）</label><input class="form-control" type="url" name="source_url" value="{{ old('source_url', $schedule->source_url) }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">注意書き</label><input class="form-control" name="notice" value="{{ old('notice', $schedule->notice) }}"></div>

    <div class="schedule-table-wrap">
      <table class="schedule-edit-table">
        <thead><tr><th style="width:70px">月</th><th style="width:130px">日（曜日）</th><th style="width:250px">トーナメント名</th><th style="width:105px">出場資格</th><th style="width:75px">地区</th><th style="width:210px">会場</th><th>ポイント</th><th>AVG</th><th>賞金</th><th>公式<br>タイトル</th><th style="width:180px">備考</th><th style="width:210px">大会との紐づけ</th><th style="width:95px">種類・削除</th></tr></thead>
        <tbody id="schedule-rows">
        @for($month = 1; $month <= 12; $month++)
          @php $monthRows = collect($displayRows->get($month, [])); @endphp
          @if($monthRows->isEmpty())
            <tr data-month-placeholder="{{ $month }}"><th class="month-cell"><div class="schedule-month-actions"><span>{{ $month }}月</span><button type="button" class="btn btn-primary btn-sm" onclick="addScheduleRow({{ $month }}, this)">＋行追加</button></div></th><td colspan="12" class="text-muted">未登録</td></tr>
          @else
            @foreach($monthRows as $row)
              @php
                $value = fn($key) => is_array($row) ? ($row[$key] ?? null) : $row->{$key};
                $rowId = $value('id');
                $rowType = $value('row_type') ?: 'event';
                $tournamentId = $value('tournament_id');
              @endphp
              <tr data-month="{{ $month }}" class="{{ $rowType === 'qualifier' ? 'qualifier-row' : '' }}">
                <th class="month-cell"><div class="schedule-month-actions"><span>{{ $month }}月</span><button type="button" class="btn btn-primary btn-sm" onclick="addScheduleRow({{ $month }}, this)">＋行追加</button></div><input type="hidden" name="rows[{{ $rowIndex }}][month]" value="{{ $month }}"><input type="hidden" name="rows[{{ $rowIndex }}][id]" value="{{ $rowId }}"></th>
                <td><input name="rows[{{ $rowIndex }}][date_label]" value="{{ $value('date_label') }}"><input type="date" class="mt-1" name="rows[{{ $rowIndex }}][start_date]" value="{{ $value('start_date') instanceof \Carbon\CarbonInterface ? $value('start_date')->toDateString() : $value('start_date') }}"><input type="date" class="mt-1" name="rows[{{ $rowIndex }}][end_date]" value="{{ $value('end_date') instanceof \Carbon\CarbonInterface ? $value('end_date')->toDateString() : $value('end_date') }}"></td>
                <td><textarea name="rows[{{ $rowIndex }}][title]">{{ $value('title') }}</textarea></td>
                <td><textarea name="rows[{{ $rowIndex }}][eligibility]">{{ $value('eligibility') }}</textarea></td>
                <td><textarea name="rows[{{ $rowIndex }}][region]">{{ $value('region') }}</textarea></td>
                <td><textarea name="rows[{{ $rowIndex }}][venue]">{{ $value('venue') }}</textarea></td>
                @foreach(['point_mark','average_mark','prize_mark','title_mark'] as $mark)<td><input class="mark-input" name="rows[{{ $rowIndex }}][{{ $mark }}]" value="{{ $value($mark) }}"></td>@endforeach
                <td><textarea name="rows[{{ $rowIndex }}][note]">{{ $value('note') }}</textarea></td>
                <td><select name="rows[{{ $rowIndex }}][tournament_id]"><option value="">紐づけなし</option>@foreach($tournaments as $tournament)<option value="{{ $tournament->id }}" {{ (string)$tournamentId === (string)$tournament->id ? 'selected' : '' }}>{{ $tournament->start_date?->format('n/j') }} {{ $tournament->name }} / {{ $tournament->venue_name }}</option>@endforeach</select>@if($tournamentId)<span class="schedule-linked">大会ID {{ $tournamentId }} に紐づけ済み</span>@endif</td>
                <td><select name="rows[{{ $rowIndex }}][row_type]">@foreach(['event'=>'大会・行事','qualifier'=>'予選（青）','note'=>'備考のみ','placeholder'=>'空欄'] as $type => $label)<option value="{{ $type }}" {{ $rowType === $type ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="rows[{{ $rowIndex }}][delete]" value="1"><span class="form-check-label text-danger">削除</span></label></td>
              </tr>
              @php $rowIndex++; @endphp
            @endforeach
          @endif
        @endfor
        </tbody>
      </table>
    </div>
    <div class="schedule-save-bar"><a class="btn btn-outline-secondary" href="{{ route('management.home') }}">管理ホームへ戻る</a><button class="btn btn-primary px-4">予定表を保存</button></div>
  </form>
</div>

<template id="schedule-row-template">
  <tr data-month="__MONTH__"><th class="month-cell"><div class="schedule-month-actions"><span>__MONTH__月</span><button type="button" class="btn btn-primary btn-sm" onclick="addScheduleRow(__MONTH__, this)">＋行追加</button></div><input type="hidden" name="rows[__INDEX__][month]" value="__MONTH__"></th>
    <td><input name="rows[__INDEX__][date_label]" placeholder="例：12（月）－14（水）"><input type="date" class="mt-1" name="rows[__INDEX__][start_date]"><input type="date" class="mt-1" name="rows[__INDEX__][end_date]"></td>
    <td><textarea name="rows[__INDEX__][title]"></textarea></td><td><textarea name="rows[__INDEX__][eligibility]"></textarea></td><td><textarea name="rows[__INDEX__][region]"></textarea></td><td><textarea name="rows[__INDEX__][venue]"></textarea></td>
    <td><input class="mark-input" name="rows[__INDEX__][point_mark]"></td><td><input class="mark-input" name="rows[__INDEX__][average_mark]"></td><td><input class="mark-input" name="rows[__INDEX__][prize_mark]"></td><td><input class="mark-input" name="rows[__INDEX__][title_mark]"></td><td><textarea name="rows[__INDEX__][note]"></textarea></td>
    <td><select name="rows[__INDEX__][tournament_id]"><option value="">紐づけなし</option>@foreach($tournaments as $tournament)<option value="{{ $tournament->id }}">{{ $tournament->start_date?->format('n/j') }} {{ $tournament->name }} / {{ $tournament->venue_name }}</option>@endforeach</select></td>
    <td><select name="rows[__INDEX__][row_type]"><option value="event">大会・行事</option><option value="qualifier">予選（青）</option><option value="note">備考のみ</option><option value="placeholder">空欄</option></select></td>
  </tr>
</template>

<script>
  let annualScheduleRowIndex = {{ $rowIndex }};
  function addScheduleRow(month, button) {
    const placeholder = document.querySelector(`[data-month-placeholder="${month}"]`);
    if (placeholder) placeholder.remove();
    const html = document.getElementById('schedule-row-template').innerHTML
      .replaceAll('__INDEX__', annualScheduleRowIndex++)
      .replaceAll('__MONTH__', month);
    const holder = document.createElement('tbody'); holder.innerHTML = html.trim();
    const newRow = holder.firstElementChild;
    const monthRows = [...document.querySelectorAll(`#schedule-rows tr[data-month="${month}"]`)];
    if (monthRows.length) monthRows[monthRows.length - 1].after(newRow); else document.getElementById('schedule-rows').appendChild(newRow);
    newRow.querySelector('input[name$="[date_label]"]').focus();
  }
</script>
@endsection
