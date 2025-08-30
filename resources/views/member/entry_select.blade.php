@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-3">大会エントリー選択</h2>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="POST" action="{{ route('tournament.entry.select.store') }}">
    @csrf

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>大会名</th>
            <th>期間</th>
            <th style="min-width: 180px;">エントリー</th>
            <th style="min-width: 360px;">操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tournaments as $tournament)
            @php
              /** @var \App\Models\TournamentEntry|null $entry */
              $entry    = $entries[$tournament->id] ?? null;
              $status   = $entry->status ?? 'no_entry';
              $hasBalls = (int)($entry->balls_count ?? 0) > 0; // withCount('balls') が無い場合は 0 扱い
            @endphp

            <tr>
              <td>{{ $tournament->name }}</td>
              <td>
                {{ optional($tournament->entry_start)->format('Y-m-d H:i:s') }}
                〜 {{ optional($tournament->entry_end)->format('Y-m-d H:i:s') }}
              </td>

              {{-- エントリーON/OFF --}}
              <td>
                <select name="entries[{{ $tournament->id }}]" class="form-select">
                  <option value="entry"    {{ $status === 'entry' ? 'selected' : '' }}>エントリーする</option>
                  <option value="no_entry" {{ $status === 'no_entry' ? 'selected' : '' }}>エントリーしない</option>
                </select>
              </td>

              {{-- 操作（この列だけにボタン/バッジをまとめる） --}}
              <td class="text-nowrap">
                @if ($entry && $status === 'entry')
                  <div class="d-flex flex-wrap gap-2">
                    {{-- 大会使用ボール登録 --}}
                    <a href="{{ route('member.entries.balls.edit', $entry->id) }}"
                       class="btn btn-outline-primary btn-sm">
                      大会使用ボール登録
                    </a>

                    {{-- シフト抽選 --}}
                    @if (empty($entry->shift))
                      <form action="{{ route('member.entries.shift.draw', $entry->id) }}"
                            method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-success btn-sm">シフト抽選</button>
                      </form>
                    @else
                      <span class="badge bg-info">シフト: {{ $entry->shift }}</span>
                    @endif

                    {{-- レーン抽選（シフト確定後のみ） --}}
                    @if (!empty($entry->shift) && empty($entry->lane))
                      <form action="{{ route('member.entries.lane.draw', $entry->id) }}"
                            method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm">レーン抽選</button>
                      </form>
                    @elseif (!empty($entry->lane))
                      <span class="badge bg-secondary">レーン: {{ $entry->lane }}</span>
                    @endif

                    {{-- 登録済みバッジ（個数は出さず有無だけ） --}}
                    @if ($hasBalls)
                      <span class="badge bg-success">ボール登録済み</span>
                    @endif
                  </div>
                @else
                  <span class="text-muted">エントリーで有効化</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <button class="btn btn-primary mt-2">保存する</button>
  </form>
</div>
@endsection