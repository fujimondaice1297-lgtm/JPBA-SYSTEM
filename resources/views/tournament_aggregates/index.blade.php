@extends('layouts.app')

@section('content')
@php
  $isGroupCompetition = in_array($tournament->competition_type, ['doubles', 'team'], true);
  $defaultSubjectType = $isGroupCompetition ? 'group' : 'individual';
  $defaultCode = $isGroupCompetition ? 'team-total' : 'all-events';
  $defaultName = $isGroupCompetition ? 'チーム合算成績' : 'オールエベンツ';
  $allStages = collect($stagesByTournament)->flatten()->filter()->unique()->sort()->values();
@endphp

<style>
  .aggregate-page { max-width: 1500px; margin: 0 auto; }
  .aggregate-header { border-bottom: 1px solid #d9dee5; padding-bottom: 14px; margin-bottom: 22px; }
  .aggregate-section { border-top: 3px solid #334155; padding: 18px 0 26px; }
  .aggregate-panel { border: 1px solid #cfd6de; border-radius: 6px; padding: 16px; margin-bottom: 18px; background: #fff; }
  .aggregate-table { font-size: .9rem; }
  .aggregate-table th { white-space: nowrap; background: #f4f6f8; }
  .aggregate-table td { vertical-align: middle; }
  .aggregate-breakdown { min-width: 250px; }
  .aggregate-breakdown div + div { margin-top: 3px; }
  .aggregate-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .aggregate-status { min-width: 72px; display: inline-block; text-align: center; }
  @media (max-width: 767px) {
    .aggregate-panel { padding: 12px; }
    .aggregate-table { font-size: .82rem; }
  }
</style>

<div class="aggregate-page">
  <div class="aggregate-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h2 class="h4 mb-1">編成・合算成績</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="aggregate-actions">
      <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-outline-secondary btn-sm">大会編集</a>
      <a href="{{ route('tournaments.results.index', $tournament->id) }}" class="btn btn-outline-secondary btn-sm">成績一覧</a>
      <a href="{{ route('tournaments.result_snapshots.index', $tournament->id) }}" class="btn btn-outline-secondary btn-sm">正式成績</a>
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if ($isGroupCompetition)
    <section class="aggregate-section">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h5 mb-0">{{ $tournament->competition_type === 'doubles' ? 'ダブルス編成' : 'チーム編成' }}</h3>
        <span class="badge text-bg-light border">{{ $groups->count() }}組</span>
      </div>

      <form method="POST" action="{{ route('tournaments.aggregate_results.groups.store', $tournament) }}" class="row g-2 align-items-end mb-4">
        @csrf
        <div class="col-md-2">
          <label class="form-label">編成コード</label>
          <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="A01">
        </div>
        <div class="col-md-3">
          <label class="form-label">編成名</label>
          <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">部門</label>
          <input type="text" name="division" class="form-control" value="{{ old('division') }}" placeholder="男子 / 女子 / 混合">
        </div>
        <div class="col-md-2">
          <label class="form-label">人数</label>
          <input type="number" name="expected_member_count" class="form-control"
                 value="{{ old('expected_member_count', $tournament->competition_type === 'doubles' ? 2 : 4) }}"
                 min="2" max="20" {{ $tournament->competition_type === 'doubles' ? 'readonly' : '' }} required>
        </div>
        <div class="col-md-1">
          <label class="form-label">表示順</label>
          <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">編成を追加</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered aggregate-table mb-0">
          <thead>
            <tr>
              <th>コード</th>
              <th>編成名</th>
              <th>部門</th>
              <th>メンバー</th>
              <th style="width: 320px;">追加</th>
              <th style="width: 80px;"></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($groups as $group)
              <tr>
                <td>{{ $group->code ?: '-' }}</td>
                <td class="fw-semibold">{{ $group->name }}</td>
                <td>{{ $group->division ?: '-' }}</td>
                <td>
                  @forelse ($group->members as $member)
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                      <span>{{ $participantLabels[$member->tournament_participant_id] ?? ('参加者 #' . $member->tournament_participant_id) }}</span>
                      <form method="POST" action="{{ route('tournaments.aggregate_results.group_members.destroy', [$tournament, $group, $member]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="メンバーを外す">外す</button>
                      </form>
                    </div>
                  @empty
                    <span class="text-muted">未編成</span>
                  @endforelse
                  <div class="small {{ $group->members->count() === $group->expected_member_count ? 'text-success' : 'text-danger' }}">
                    {{ $group->members->count() }}/{{ $group->expected_member_count }}名
                  </div>
                </td>
                <td>
                  <form method="POST" action="{{ route('tournaments.aggregate_results.group_members.store', [$tournament, $group]) }}" class="d-flex gap-2">
                    @csrf
                    <select name="tournament_participant_id" class="form-select form-select-sm" required>
                      <option value="">参加者を選択</option>
                      @foreach ($availableParticipants as $participant)
                        <option value="{{ $participant->id }}">{{ $participantLabels[$participant->id] }}</option>
                      @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm">追加</button>
                  </form>
                </td>
                <td>
                  <form method="POST" action="{{ route('tournaments.aggregate_results.groups.destroy', [$tournament, $group]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">編成はまだありません。</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  @endif

  <section class="aggregate-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="h5 mb-0">合算定義</h3>
      <span class="badge text-bg-light border">{{ $definitions->count() }}件</span>
    </div>

    <form method="POST" action="{{ route('tournaments.aggregate_results.definitions.store', $tournament) }}" class="row g-2 align-items-end mb-4">
      @csrf
      <div class="col-md-2">
        <label class="form-label">コード</label>
        <input type="text" name="code" class="form-control" value="{{ old('code', $defaultCode) }}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">成績名</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $defaultName) }}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">合算対象</label>
        <select name="subject_type" class="form-select">
          <option value="individual" {{ old('subject_type', $defaultSubjectType) === 'individual' ? 'selected' : '' }}>個人</option>
          <option value="group" {{ old('subject_type', $defaultSubjectType) === 'group' ? 'selected' : '' }}>編成</option>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label">性別</label>
        <select name="gender" class="form-select">
          <option value="">指定なし</option>
          <option value="M">男子</option>
          <option value="F">女子</option>
          <option value="X">混合</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">同ピン順位</label>
        <select name="tie_break_policy" class="form-select">
          <option value="shared_rank" {{ old('tie_break_policy', 'shared_rank') === 'shared_rank' ? 'selected' : '' }}>同順位</option>
          <option value="low_high" {{ old('tie_break_policy') === 'low_high' ? 'selected' : '' }}>ローハイ差</option>
        </select>
      </div>
      <div class="col-md-2">
        <div class="form-check mb-2">
          <input type="checkbox" name="require_all_sources" value="1" class="form-check-input" id="new-require-all" checked>
          <label for="new-require-all" class="form-check-label">全競技を必須</label>
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_published" value="1" class="form-check-input" id="new-published">
          <label for="new-published" class="form-check-label">公開対象</label>
        </div>
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-primary w-100">定義を追加</button>
      </div>
    </form>

    @forelse ($definitions as $definition)
      @php
        $currentSnapshot = $definition->snapshots->first();
        $diagnostics = data_get($currentSnapshot?->calculation_definition, 'diagnostics', []);
      @endphp
      <article class="aggregate-panel">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <h4 class="h6 mb-0">{{ $definition->name }}</h4>
              <span class="badge text-bg-secondary">{{ $definition->subject_type === 'group' ? '編成' : '個人' }}</span>
              @if ($definition->is_published)
                <span class="badge text-bg-success">公開対象</span>
              @endif
            </div>
            <div class="small text-muted mt-1">{{ $definition->code }}</div>
          </div>
          <div class="aggregate-actions">
            <form method="POST" action="{{ route('tournaments.aggregate_results.calculate', [$tournament, $definition]) }}">
              @csrf
              <button type="submit" class="btn btn-success btn-sm">合算を再計算</button>
            </form>
            <form method="POST" action="{{ route('tournaments.aggregate_results.definitions.destroy', [$tournament, $definition]) }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-outline-danger btn-sm">定義を削除</button>
            </form>
          </div>
        </div>

        <form method="POST" action="{{ route('tournaments.aggregate_results.definitions.store', $tournament) }}" class="row g-2 align-items-end mb-4">
          @csrf
          <input type="hidden" name="definition_id" value="{{ $definition->id }}">
          <div class="col-md-2">
            <label class="form-label small">コード</label>
            <input type="text" name="code" class="form-control form-control-sm" value="{{ $definition->code }}" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small">成績名</label>
            <input type="text" name="name" class="form-control form-control-sm" value="{{ $definition->name }}" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small">合算対象</label>
            <select name="subject_type" class="form-select form-select-sm">
              <option value="individual" {{ $definition->subject_type === 'individual' ? 'selected' : '' }}>個人</option>
              <option value="group" {{ $definition->subject_type === 'group' ? 'selected' : '' }}>編成</option>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label small">性別</label>
            <select name="gender" class="form-select form-select-sm">
              <option value="" {{ !$definition->gender ? 'selected' : '' }}>指定なし</option>
              <option value="M" {{ $definition->gender === 'M' ? 'selected' : '' }}>男子</option>
              <option value="F" {{ $definition->gender === 'F' ? 'selected' : '' }}>女子</option>
              <option value="X" {{ $definition->gender === 'X' ? 'selected' : '' }}>混合</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">同ピン順位</label>
            <select name="tie_break_policy" class="form-select form-select-sm">
              <option value="shared_rank" {{ $definition->tie_break_policy === 'shared_rank' ? 'selected' : '' }}>同順位</option>
              <option value="low_high" {{ $definition->tie_break_policy === 'low_high' ? 'selected' : '' }}>ローハイ差</option>
            </select>
          </div>
          <div class="col-md-2">
            <div class="form-check mb-1">
              <input type="checkbox" name="require_all_sources" value="1" class="form-check-input"
                     id="require-all-{{ $definition->id }}" {{ $definition->require_all_sources ? 'checked' : '' }}>
              <label for="require-all-{{ $definition->id }}" class="form-check-label small">全競技を必須</label>
            </div>
            <div class="form-check">
              <input type="checkbox" name="is_published" value="1" class="form-check-input"
                     id="published-{{ $definition->id }}" {{ $definition->is_published ? 'checked' : '' }}>
              <label for="published-{{ $definition->id }}" class="form-check-label small">公開対象</label>
            </div>
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-outline-primary btn-sm w-100">定義を更新</button>
          </div>
        </form>

        <h5 class="h6">合算元</h5>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered aggregate-table mb-0">
            <thead>
              <tr><th>競技</th><th>表示名</th><th>ステージ</th><th>ゲーム範囲</th><th>予定G/人</th><th>必須</th><th></th></tr>
            </thead>
            <tbody>
              @forelse ($definition->sources as $source)
                <tr>
                  <td>{{ $source->sourceTournament?->name ?? ('大会 #' . $source->source_tournament_id) }}</td>
                  <td>{{ $source->label }}</td>
                  <td>{{ $source->stage ?: '全ステージ' }}</td>
                  <td>{{ $source->game_from ? ($source->game_from . '-' . $source->game_to . 'G') : '全ゲーム' }}</td>
                  <td>{{ $source->expected_games_per_member ?: '-' }}</td>
                  <td>{{ $source->is_required ? '必須' : '任意' }}</td>
                  <td>
                    <form method="POST" action="{{ route('tournaments.aggregate_results.sources.destroy', [$tournament, $definition, $source]) }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted">合算元はまだありません。</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <form method="POST" action="{{ route('tournaments.aggregate_results.sources.store', [$tournament, $definition]) }}" class="row g-2 align-items-end mb-4">
          @csrf
          <div class="col-md-3">
            <label class="form-label small">対象競技</label>
            <select name="source_tournament_id" class="form-select form-select-sm" required>
              @foreach ($candidateTournaments as $candidate)
                @if ($definition->subject_type !== 'group' || $candidate->id === $tournament->id)
                  <option value="{{ $candidate->id }}" {{ $candidate->id === $tournament->id ? 'selected' : '' }}>
                    {{ $candidate->year }} {{ $candidate->name }}
                  </option>
                @endif
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">表示名</label>
            <input type="text" name="label" class="form-control form-control-sm"
                   value="{{ $definition->subject_type === 'group' ? 'チーム戦' : '' }}" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small">ステージ</label>
            <input type="text" name="stage" class="form-control form-control-sm" list="aggregate-stages" placeholder="空欄は全ステージ">
          </div>
          <div class="col-md-1">
            <label class="form-label small">開始G</label>
            <input type="number" name="game_from" class="form-control form-control-sm" min="1">
          </div>
          <div class="col-md-1">
            <label class="form-label small">終了G</label>
            <input type="number" name="game_to" class="form-control form-control-sm" min="1">
          </div>
          <div class="col-md-1">
            <label class="form-label small">予定G/人</label>
            <input type="number" name="expected_games_per_member" class="form-control form-control-sm" min="1">
          </div>
          <div class="col-md-1">
            <div class="form-check mb-2">
              <input type="checkbox" name="is_required" value="1" class="form-check-input"
                     id="required-source-{{ $definition->id }}" checked>
              <label for="required-source-{{ $definition->id }}" class="form-check-label small">必須</label>
            </div>
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-outline-primary btn-sm w-100">追加</button>
          </div>
        </form>

        @if ($currentSnapshot)
          <div class="d-flex flex-wrap gap-3 align-items-center mb-2 small">
            <span>計算日時: {{ optional($currentSnapshot->reflected_at)->format('Y/m/d H:i') }}</span>
            <span>対象: {{ $currentSnapshot->rows->count() }}件</span>
            @if ((int) data_get($diagnostics, 'unassigned_score_rows', 0) > 0)
              <span class="badge text-bg-warning">未編成スコア {{ data_get($diagnostics, 'unassigned_score_rows') }}件</span>
            @endif
            @if ((int) data_get($diagnostics, 'unverified_identity_rows', 0) > 0)
              <span class="badge text-bg-warning">仮照合 {{ data_get($diagnostics, 'unverified_identity_rows') }}件</span>
            @endif
          </div>
          <div class="table-responsive">
            <table class="table table-bordered aggregate-table mb-0">
              <thead>
                <tr><th>順位</th><th>選手／編成</th><th>状態</th><th>ゲーム</th><th>合計</th><th>AVE</th><th>競技別内訳</th></tr>
              </thead>
              <tbody>
                @forelse ($currentSnapshot->rows as $row)
                  @php
                    $rowSources = data_get($row->breakdown, 'sources', []);
                    $incompleteReasons = data_get($row->breakdown, 'incomplete_reasons', []);
                  @endphp
                  <tr class="{{ $row->is_complete ? '' : 'table-warning' }}">
                    <td>{{ $row->is_complete ? $row->ranking : '-' }}</td>
                    <td>
                      <div class="fw-semibold">{{ $row->display_name }}</div>
                      @if ($row->entry_number)<div class="small text-muted">{{ $row->entry_number }}</div>@endif
                    </td>
                    <td>
                      <span class="badge aggregate-status {{ $row->is_complete ? 'text-bg-success' : 'text-bg-warning' }}">
                        {{ $row->is_complete ? '集計完了' : '未完了' }}
                      </span>
                      @foreach ($incompleteReasons as $reason)
                        <div class="small text-danger mt-1">{{ $reason }}</div>
                      @endforeach
                    </td>
                    <td>{{ number_format($row->games) }}G</td>
                    <td class="fw-semibold">{{ number_format($row->total_pin) }}</td>
                    <td>{{ $row->average !== null ? number_format((float) $row->average, 2) : '-' }}</td>
                    <td class="aggregate-breakdown">
                      @foreach ($rowSources as $sourceRow)
                        <div>
                          <span class="text-muted">{{ $sourceRow['label'] ?? '競技' }}</span>
                          {{ number_format((int) ($sourceRow['total_pin'] ?? 0)) }}
                          <span class="small text-muted">({{ (int) ($sourceRow['games'] ?? 0) }}G)</span>
                        </div>
                      @endforeach
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted">対象スコアはありません。</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        @else
          <div class="text-muted small">未計算</div>
        @endif
      </article>
    @empty
      <div class="text-muted py-3">合算定義はまだありません。</div>
    @endforelse
  </section>
</div>

<datalist id="aggregate-stages">
  @foreach ($allStages as $stage)
    <option value="{{ $stage }}">
  @endforeach
</datalist>
@endsection
