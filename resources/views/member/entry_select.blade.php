@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-3">大会エントリー選択</h2>

  @php
    $isAllowed = (bool) ($eligibility['allowed'] ?? false);
  @endphp

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card mb-4">
    <div class="card-header fw-bold">現在のエントリー判定</div>
    <div class="card-body">
      <div class="row g-2">
        <div class="col-md-3">
          <div class="text-muted small">ライセンスNo</div>
          <div>{{ $bowler->license_no ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">氏名</div>
          <div>{{ $bowler->name_kanji ?? '-' }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">会員区分</div>
          <div>{{ $eligibility['member_class_label'] ?? '-' }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">有効状態</div>
          <div>{{ $eligibility['active_label'] ?? '-' }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">公式戦出場</div>
          <div>{{ $eligibility['official_entry_label'] ?? '-' }}</div>
        </div>
      </div>

      @if (!$isAllowed)
        <div class="alert alert-warning mt-3 mb-0">
          {{ $eligibility['message'] ?? '現在の会員状態では大会エントリーを利用できません。' }}
        </div>
      @endif
    </div>
  </div>

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
          @forelse ($tournaments as $tournament)
            @php
              /** @var \App\Models\TournamentEntry|null $entry */
              $entry    = $entries[$tournament->id] ?? null;
              $status   = $entry->status ?? 'no_entry';
              $hasBalls = (int) ($entry->balls_count ?? 0) > 0;
            @endphp

            <tr>
              <td>{{ $tournament->name }}</td>
              <td>
                {{ optional($tournament->entry_start)->format('Y-m-d H:i:s') }}
                〜 {{ optional($tournament->entry_end)->format('Y-m-d H:i:s') }}
              </td>

              <td>
                @if ($isAllowed)
                  <select name="entries[{{ $tournament->id }}]" class="form-select">
                    <option value="entry" {{ $status === 'entry' ? 'selected' : '' }}>エントリーする</option>
                    <option value="no_entry" {{ $status === 'no_entry' ? 'selected' : '' }}>エントリーしない</option>
                  </select>
                @else
                  <input type="text" class="form-control" value="対象外" disabled>
                @endif
              </td>

              <td class="text-nowrap">
                @if (!$isAllowed)
                  <span class="text-muted">{{ $eligibility['message'] ?? 'エントリー対象外です。' }}</span>
                @elseif ($entry && $status === 'entry')
                  <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('member.entries.balls.edit', $entry->id) }}"
                       class="btn btn-outline-primary btn-sm">
                      大会使用ボール登録
                    </a>

                    @if (empty($entry->shift))
                      <form action="{{ route('member.entries.shift.draw', $entry->id) }}"
                            method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-success btn-sm">シフト抽選</button>
                      </form>
                    @else
                      <span class="badge bg-info">シフト: {{ $entry->shift }}</span>
                    @endif

                    @if (!empty($entry->shift) && empty($entry->lane))
                      <form action="{{ route('member.entries.lane.draw', $entry->id) }}"
                            method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm">レーン抽選</button>
                      </form>
                    @elseif (!empty($entry->lane))
                      <span class="badge bg-secondary">レーン: {{ $entry->lane }}</span>
                    @endif

                    @if ($hasBalls)
                      <span class="badge bg-success">ボール登録済み</span>
                    @endif
                  </div>
                @else
                  <span class="text-muted">エントリーで有効化</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted">現在受付中の大会はありません。</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($isAllowed)
      <button class="btn btn-primary mt-2">保存する</button>
    @endif
  </form>
</div>
@endsection