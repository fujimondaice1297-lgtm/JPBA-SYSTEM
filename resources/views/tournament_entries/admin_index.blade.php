@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">大会エントリー一覧（管理）</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ戻る</a>
      <a href="{{ route('tournaments.seed_players.index', $tournament->id) }}" class="btn btn-outline-success">優先出場者一覧</a>
      <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-dark">抽選一覧</a>
      <a href="{{ route('tournaments.lane_movement_table.show', $tournament->id) }}" class="btn btn-outline-info">レーン移動表</a>
      <a href="{{ route('tournaments.draws.index', ['tournament' => $tournament->id, 'pending_draw' => 1]) }}" class="btn btn-outline-secondary">未抽選一覧</a>
      <a href="{{ route('member.tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-primary">参加選手向け一覧</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>入力内容に誤りがあります：</strong>
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="alert alert-info small">
    優先出場者一覧に登録済みの選手は、この画面の「優先出場」列に表示します。
    表示順は「参加権利あり」を最優先にし、その中で参加/ウェイティング、優先出場順位、待機順を見ます。
    参加権利がない選手は、優先出場者であっても一括繰り上げ対象から外します。
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">参加</div>
        <div class="fs-4 fw-bold">{{ $summary['entry_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">ウェイティング</div>
        <div class="fs-4 fw-bold">{{ $summary['waitlist_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card border-success"><div class="card-body">
        <div class="text-muted small">優先出場 参加</div>
        <div class="fs-4 fw-bold text-success">{{ $summary['priority_entry_count'] ?? 0 }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card border-warning"><div class="card-body">
        <div class="text-muted small">優先出場 待機</div>
        <div class="fs-4 fw-bold text-warning">{{ $summary['priority_waitlist_count'] ?? 0 }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card border-danger"><div class="card-body">
        <div class="text-muted small">優先出場 未登録</div>
        <div class="fs-4 fw-bold text-danger">{{ $summary['priority_missing_count'] ?? 0 }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">希望シフトあり</div>
        <div class="fs-4 fw-bold">{{ $summary['preferred_shift_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">チェックイン済み</div>
        <div class="fs-4 fw-bold">{{ $summary['checked_in_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">シフト未抽選</div>
        <div class="fs-4 fw-bold">{{ $summary['pending_shift_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card"><div class="card-body">
        <div class="text-muted small">レーン未抽選</div>
        <div class="fs-4 fw-bold">{{ $summary['pending_lane_count'] }}</div>
      </div></div>
    </div>
  </div>

  @if (($summary['priority_missing_count'] ?? 0) > 0)
    <div class="card border-danger mb-4">
      <div class="card-header fw-bold text-danger">優先出場者の未登録チェック</div>
      <div class="card-body">
        <p class="small text-muted mb-3">
          優先出場者一覧に入っていますが、この大会のエントリー / ウェイティングにはまだ存在しない選手です。
          必要に応じて、この表の「ウェイティング登録」ボタン、または下の「ウェイティング登録」から登録してください。
        </p>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead>
              <tr>
                <th>優先順</th>
                <th>種別</th>
                <th>由来</th>
                <th>ライセンスNo</th>
                <th>氏名</th>
                <th>備考</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach (($summary['priority_missing_entries'] ?? []) as $priorityMissing)
                @php
                  $missingLicenseNo = trim((string) ($priorityMissing['license_no'] ?? ''));
                  $missingPrioritySort = (int) ($priorityMissing['priority_sort'] ?? 999999);
                  $missingWaitlistPriority = $missingPrioritySort > 0 && $missingPrioritySort < 999999 ? $missingPrioritySort : null;
                  $missingNoteParts = array_filter([
                    '優先出場未登録から登録',
                    $priorityMissing['priority_source_label'] ?? null,
                    $priorityMissing['priority_label'] ?? null,
                  ]);
                @endphp
                <tr>
                  <td>{{ $priorityMissing['priority_order_label'] ?? '-' }}</td>
                  <td>{{ $priorityMissing['priority_label'] ?? '-' }}</td>
                  <td>{{ $priorityMissing['priority_source_label'] ?? '-' }}</td>
                  <td>{{ $missingLicenseNo !== '' ? $missingLicenseNo : '-' }}</td>
                  <td>{{ $priorityMissing['name_kanji'] ?? '-' }}</td>
                  <td>
                    <span class="badge bg-light text-dark" title="{{ $priorityMissing['eligibility_message'] ?? '' }}">
                      {{ $priorityMissing['eligibility_short'] ?? '-' }}
                    </span>
                  </td>
                  <td class="small">{{ $priorityMissing['priority_note'] ?? '-' }}</td>
                  <td>
                    @if ($missingLicenseNo !== '' && (($priorityMissing['eligibility_short'] ?? '') === '参加権利あり'))
                      <form method="POST" action="{{ route('tournaments.waitlist.store', $tournament->id) }}" class="m-0">
                        @csrf
                        <input type="hidden" name="license_no" value="{{ $missingLicenseNo }}">
                        @if (!is_null($missingWaitlistPriority))
                          <input type="hidden" name="waitlist_priority" value="{{ $missingWaitlistPriority }}">
                        @endif
                        <input type="hidden" name="waitlist_note" value="{{ implode(' / ', $missingNoteParts) }}">
                        <button type="submit"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('この優先出場者をウェイティング登録します。よろしいですか？');">
                          ウェイティング登録
                        </button>
                      </form>
                    @else
                      <span class="text-muted small">
                        {{ $missingLicenseNo === '' ? 'ライセンスNo未設定' : '参加権利なし' }}
                      </span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header fw-bold">ウェイティング登録</div>
    <div class="card-body">
      <form method="POST" action="{{ route('tournaments.waitlist.store', $tournament->id) }}">
        @csrf
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">ライセンスNo</label>
            <input type="text" name="license_no" value="{{ old('license_no') }}" class="form-control" placeholder="例: M00001297 / 1297">
            <div class="form-text">下4桁入力時は、大会の対象性別を優先して照合します。</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">優先順</label>
            <input type="number" name="waitlist_priority" value="{{ old('waitlist_priority') }}" class="form-control" min="1" max="9999">
            <div class="form-text">空欄なら優先出場設定から補完します。</div>
          </div>
          <div class="col-md-5">
            <label class="form-label">備考</label>
            <input type="text" name="waitlist_note" value="{{ old('waitlist_note') }}" class="form-control" maxlength="2000" placeholder="例: 権利外だが欠員時に繰り上げ候補">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">ウェイティング登録</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <form method="GET" action="{{ route('tournaments.entries.index', $tournament->id) }}" class="mb-3">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">検索</label>
        <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="ライセンスNo / 氏名 / フリガナ">
      </div>
      <div class="col-md-3">
        <label class="form-label">状態</label>
        <select name="status" class="form-select">
          <option value="active" {{ $status === 'active' ? 'selected' : '' }}>参加 + ウェイティング</option>
          <option value="entry" {{ $status === 'entry' ? 'selected' : '' }}>参加のみ</option>
          <option value="waiting" {{ $status === 'waiting' ? 'selected' : '' }}>ウェイティングのみ</option>
          <option value="no_entry" {{ $status === 'no_entry' ? 'selected' : '' }}>不参加のみ</option>
        </select>
      </div>
      <div class="col-md-5 d-flex gap-2">
        <button type="submit" class="btn btn-primary">絞り込む</button>
        <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-secondary">リセット</a>
      </div>
    </div>
  </form>

  <form id="bulk-promote-waitlist-form"
        method="POST"
        action="{{ route('tournaments.waitlist.bulk_promote', $tournament->id) }}"
        class="d-none">
    @csrf
  </form>

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <div class="small text-muted">
        チェックしたウェイティング行をまとめて参加へ繰り上げます。参加権利がない行は選択できません。
      </div>
      <button type="submit"
              form="bulk-promote-waitlist-form"
              class="btn btn-success"
              onclick="return confirm('チェックしたウェイティング行を参加へ一括繰り上げします。よろしいですか？');">
        チェックしたウェイティングを一括参加登録
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th>選択</th>
          <th>状態</th>
          <th>優先出場</th>
          <th>待機順</th>
          <th>ライセンスNo</th>
          <th>氏名</th>
          <th>参加権利</th>
          <th>希望シフト</th>
          <th>シフト</th>
          <th>レーン</th>
          <th>ボール数</th>
          <th>チェックイン</th>
          <th>備考</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $entry)
          @php
            $bowler = $entry->bowler;
          @endphp
          <tr class="{{ $entry->is_priority_entry ? 'table-success' : '' }}">
            <td class="text-center">
              @if ($entry->status === 'waiting' && $entry->eligibility_short === '参加権利あり')
                <input type="checkbox"
                       name="entry_ids[]"
                       value="{{ $entry->id }}"
                       form="bulk-promote-waitlist-form"
                       class="form-check-input"
                       title="一括参加登録の対象にする">
              @elseif ($entry->status === 'waiting')
                <span class="text-muted small" title="{{ $entry->eligibility_message }}">対象外</span>
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
            <td>
              @if ($entry->status === 'entry')
                <span class="badge bg-primary">参加</span>
              @elseif ($entry->status === 'waiting')
                <span class="badge bg-warning text-dark">ウェイティング</span>
              @else
                <span class="badge bg-secondary">{{ $entry->status_label }}</span>
              @endif
            </td>
            <td>
              @if ($entry->is_priority_entry)
                <div class="d-flex flex-column gap-1">
                  <div>
                    <span class="badge {{ $entry->priority_badge_class }}">
                      優先 {{ $entry->priority_order_label }}
                    </span>
                  </div>
                  <div class="small fw-bold">{{ $entry->priority_label }}</div>
                  <div class="small text-muted">{{ $entry->priority_source_label }}</div>
                  @if ($entry->priority_note)
                    <div class="small text-muted">{{ $entry->priority_note }}</div>
                  @endif
                </div>
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
            <td>{{ $entry->waitlist_priority ?? '-' }}</td>
            <td>{{ $entry->participant_display_license_no ?? ($bowler->license_no ?? '-') }}</td>
            <td>{{ $entry->participant_display_name ?? ($bowler->name_kanji ?? '-') }}</td>
            <td>
              <span class="badge bg-light text-dark" title="{{ $entry->eligibility_message }}">
                {{ $entry->eligibility_short }}
              </span>
            </td>
            <td>{{ filled($entry->preferred_shift_code) ? $entry->preferred_shift_code : '-' }}</td>
           <td>
              @php
                $displayShift = $entry->participant_shift ?? $entry->shift ?? null;
              @endphp
              {{ filled($displayShift) && !in_array($displayShift, ['予選'], true) ? $displayShift : 'なし' }}
            </td>
            <td>{{ filled($entry->participant_lane_label ?? null) ? $entry->participant_lane_label : (filled($entry->lane) ? $entry->lane : '-') }}</td>
            <td>{{ $entry->balls_count }}</td>
            <td>{{ optional($entry->checked_in_at)->format('Y-m-d H:i') ?? '-' }}</td>
            <td class="small">{{ $entry->waitlist_note ?? '-' }}</td>
            <td>
              <div class="d-flex flex-wrap gap-2">
                @if ($entry->status === 'waiting')
                  <form method="POST" action="{{ route('tournaments.waitlist.promote', $entry->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">参加へ繰り上げ</button>
                  </form>
                @endif

                <a href="{{ route('tournaments.draws.index', ['tournament' => $tournament->id, 'q' => $bowler->license_no ?? '']) }}"
                   class="btn btn-sm btn-outline-secondary">
                  抽選状況
                </a>

                @if (in_array($entry->status, ['entry', 'waiting'], true))
                  <form method="POST" action="{{ route('tournaments.entries.cancel', $entry->id) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('このエントリー / ウェイティングを取り消します。抽選・待機順・チェックイン情報もクリアされます。よろしいですか？');">
                      取消
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="14" class="text-center text-muted">該当データはありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $entries->links() }}
</div>
@endsection
