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
            <th style="min-width: 180px;">希望シフト</th>
            <th style="min-width: 660px;">操作 / 当日状態</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($tournaments as $tournament)
            @php
              /** @var \App\Models\TournamentEntry|null $entry */
              $entry = $entries[$tournament->id] ?? null;
              $status = $entry->status ?? 'no_entry';
              $hasBalls = (int) ($entry->balls_count ?? 0) > 0;
              $useShiftDraw = (bool) ($tournament->use_shift_draw ?? false);
              $useLaneDraw = (bool) ($tournament->use_lane_draw ?? false);
              $acceptShiftPreference = (bool) ($tournament->accept_shift_preference ?? false);

              $shiftCodes = collect(explode(',', (string) ($tournament->shift_codes ?? '')))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

              $shiftReady = !$useShiftDraw || !empty($entry?->shift);
              $laneReady = !$useLaneDraw || !empty($entry?->lane);
              $checkedIn = !is_null($entry?->checked_in_at);
              $preferredShift = old("preferred_shifts.{$tournament->id}", $entry?->preferred_shift_code);
            @endphp

            <tr>
              <td>{{ $tournament->name }}</td>
              <td>
                {{ optional($tournament->entry_start)->format('Y-m-d H:i:s') }}
                〜 {{ optional($tournament->entry_end)->format('Y-m-d H:i:s') }}
              </td>

              <td>
                @if ($status === 'waiting')
                  <span class="badge bg-warning text-dark">ウェイティング中</span>
                @elseif ($isAllowed)
                  <select name="entries[{{ $tournament->id }}]" class="form-select">
                    <option value="entry" {{ $status === 'entry' ? 'selected' : '' }}>エントリーする</option>
                    <option value="no_entry" {{ $status === 'no_entry' ? 'selected' : '' }}>エントリーしない</option>
                  </select>
                @else
                  <input type="text" class="form-control" value="対象外" disabled>
                @endif
              </td>

              <td>
                @if ($status === 'waiting')
                  <span class="text-muted">管理者登録</span>
                @elseif ($isAllowed && $useShiftDraw && $acceptShiftPreference && $shiftCodes->isNotEmpty())
                  <select name="preferred_shifts[{{ $tournament->id }}]" class="form-select">
                    <option value="">指定なし</option>
                    @foreach ($shiftCodes as $shiftCode)
                      <option value="{{ $shiftCode }}" {{ $preferredShift === $shiftCode ? 'selected' : '' }}>
                        {{ $shiftCode }}
                      </option>
                    @endforeach
                  </select>
                @else
                  <span class="text-muted">受付なし</span>
                @endif
              </td>

              <td>
                <div class="d-flex flex-wrap gap-2">
                  <a href="{{ route('member.tournaments.entries.index', $tournament->id) }}"
                     class="btn btn-outline-secondary btn-sm">
                    参加一覧
                  </a>

                  <a href="{{ route('member.tournaments.draws.index', $tournament->id) }}"
                     class="btn btn-outline-secondary btn-sm">
                    抽選結果
                  </a>

                  @if (!$isAllowed && !$entry)
                    <span class="text-muted">{{ $eligibility['message'] ?? 'エントリー対象外です。' }}</span>
                  @elseif ($entry && $status === 'waiting')
                    <span class="badge bg-warning text-dark">ウェイティング登録済み</span>
                    @if (!is_null($entry->waitlist_priority))
                      <span class="badge bg-light text-dark">優先順: {{ $entry->waitlist_priority }}</span>
                    @endif
                  @elseif ($entry && $status === 'entry')
                    <a href="{{ route('member.entries.balls.edit', $entry->id) }}"
                       class="btn btn-outline-primary btn-sm">
                      大会使用ボール登録
                    </a>

                    @if ($useShiftDraw)
                      @if (empty($entry->shift))
                        <button type="submit"
                                class="btn btn-outline-success btn-sm"
                                form="shift-draw-form-{{ $entry->id }}">
                          シフト抽選
                        </button>
                      @else
                        <span class="badge bg-info text-dark">シフト: {{ $entry->shift }}</span>
                      @endif
                    @else
                      <span class="badge bg-light text-dark">シフト抽選なし</span>
                    @endif

                    @if ($useLaneDraw)
                      @if ((!$useShiftDraw || !empty($entry->shift)) && empty($entry->lane))
                        <button type="submit"
                                class="btn btn-outline-secondary btn-sm"
                                form="lane-draw-form-{{ $entry->id }}">
                          レーン抽選
                        </button>
                      @elseif (!empty($entry->lane))
                        <span class="badge bg-secondary">レーン: {{ $entry->lane }}</span>
                      @endif
                    @else
                      <span class="badge bg-light text-dark">レーン抽選なし</span>
                    @endif

                    @if (!empty($entry->preferred_shift_code))
                      <span class="badge bg-light text-dark">希望: {{ $entry->preferred_shift_code }}</span>
                    @endif

                    @if ($hasBalls)
                      <span class="badge bg-success">ボール登録済み</span>
                    @endif

                    @if ($checkedIn)
                      <span class="badge bg-dark">チェックイン済み</span>
                      <span class="badge bg-light text-dark">
                        {{ optional($entry->checked_in_at)->format('Y-m-d H:i') }}
                      </span>
                    @elseif ($shiftReady && $laneReady)
                      <button type="submit"
                              class="btn btn-success btn-sm"
                              form="check-in-form-{{ $entry->id }}">
                        チェックイン
                      </button>
                    @else
                      <span class="badge bg-warning text-dark">抽選完了後にチェックイン</span>
                    @endif
                  @else
                    <span class="text-muted">エントリーで有効化</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted">現在受付中の大会はありません。</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($isAllowed)
      <button class="btn btn-primary mt-2">保存する</button>
    @endif
  </form>

  @foreach ($tournaments as $tournament)
    @php
      $entry = $entries[$tournament->id] ?? null;
      $status = $entry->status ?? 'no_entry';
    @endphp

    @if ($entry && $status === 'entry')
      <form id="shift-draw-form-{{ $entry->id }}"
            action="{{ route('member.entries.shift.draw', $entry->id) }}"
            method="POST" class="d-none">
        @csrf
      </form>

      <form id="lane-draw-form-{{ $entry->id }}"
            action="{{ route('member.entries.lane.draw', $entry->id) }}"
            method="POST" class="d-none">
        @csrf
      </form>

      <form id="check-in-form-{{ $entry->id }}"
            action="{{ route('member.entries.check_in', $entry->id) }}"
            method="POST" class="d-none">
        @csrf
      </form>
    @endif
  @endforeach
</div>
@endsection