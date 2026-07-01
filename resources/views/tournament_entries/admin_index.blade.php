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

  @include('tournament_entries.partials.entry_operation_logs', ['entryOperationLogs' => $entryOperationLogs ?? collect()])

  <div id="amateur-participants" class="card mb-4 border-primary">
    <div class="card-header fw-bold text-primary">アマチュア参加者登録</div>
    <div class="card-body">
      <p class="small text-muted mb-3">
        アマチュア選手はプロボウラーのプロフィールには登録せず、アマチュア選手マスターから大会参加者として呼び出します。
        ここで登録した利き腕・所属・用品契約は、レーン移動表・速報・正式成績PDFの表示元になります。
      </p>

      <form method="POST" action="{{ route('tournaments.amateur_participants.store', $tournament->id) }}" class="mb-4">
        @csrf
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">既存アマチュア選手を呼び出す</label>
            <select name="amateur_bowler_id" class="form-select">
              <option value="">新規登録する / 既存を選ばない</option>
              @php
                $amateurBowlerOptions = $amateurBowlers ?? collect();
              @endphp
              @foreach ($amateurBowlerOptions as $amateurBowler)
                <option value="{{ $amateurBowler->id }}" {{ (string) old('amateur_bowler_id') === (string) $amateurBowler->id ? 'selected' : '' }}>
                  @if (!empty($amateurBowler->amateur_no))
                    {{ $amateurBowler->amateur_no }} /
                  @endif
                  {{ $amateurBowler->name }}
                  @if (!empty($amateurBowler->name_kana))
                    （{{ $amateurBowler->name_kana }}）
                  @endif
                  @if (!empty($amateurBowler->affiliation_name))
                    / {{ $amateurBowler->affiliation_name }}
                  @endif
                </option>
              @endforeach
            </select>
            <div class="form-text">既存選手を選ぶと、空欄項目はマスター情報で補完します。</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">識別番号</label>
            <input type="text" name="amateur_no" value="{{ old('amateur_no') }}" class="form-control" maxlength="32" placeholder="例: A000001">
            <div class="form-text">空欄なら自動採番。人物マスター用です。</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">氏名</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" maxlength="255" placeholder="例: 戸塚知菜">
            <div class="form-text">新規登録時は必須。既存選択時は表示名の上書きに使えます。</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">フリガナ</label>
            <input type="text" name="name_kana" value="{{ old('name_kana') }}" class="form-control" maxlength="255" placeholder="例: トツカ チナ">
          </div>
          <div class="col-md-2">
            <label class="form-label">性別</label>
            <select name="gender" class="form-select">
              @php
                $oldGender = old('gender', $tournament->gender ?? '');
              @endphp
              <option value="">未設定</option>
              <option value="F" {{ $oldGender === 'F' ? 'selected' : '' }}>女子</option>
              <option value="M" {{ $oldGender === 'M' ? 'selected' : '' }}>男子</option>
              <option value="X" {{ $oldGender === 'X' ? 'selected' : '' }}>共通</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">利き腕</label>
            <select name="dominant_arm" class="form-select">
              @php
                $oldDominantArm = old('dominant_arm');
              @endphp
              <option value="">未設定</option>
              <option value="右" {{ $oldDominantArm === '右' ? 'selected' : '' }}>右</option>
              <option value="左" {{ $oldDominantArm === '左' ? 'selected' : '' }}>左</option>
              <option value="両" {{ $oldDominantArm === '両' ? 'selected' : '' }}>両</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">所属ボウリング場</label>
            <input type="text" name="affiliation_name" value="{{ old('affiliation_name') }}" class="form-control" maxlength="255" placeholder="例: 山形ファミリーボウル">
          </div>
          <div class="col-md-4">
            <label class="form-label">用品契約</label>
            <input type="text" name="equipment_contract" value="{{ old('equipment_contract') }}" class="form-control" maxlength="255" placeholder="例: ABS / HI-SP など">
          </div>
          <div class="col-md-2">
            <label class="form-label">開始レーン</label>
            <input type="number" name="lane" value="{{ old('lane') }}" class="form-control" min="1" max="999" placeholder="例: 7">
          </div>
          <div class="col-md-2">
            <label class="form-label">枠</label>
            <input type="number" name="lane_slot" value="{{ old('lane_slot') }}" class="form-control" min="1" max="9" placeholder="例: 3">
            <div class="form-text">入力すると 7L-3 のように表示します。</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">BOX</label>
            <input type="number" name="box_no" value="{{ old('box_no') }}" class="form-control" min="1" max="999">
          </div>
          <div class="col-md-2">
            <label class="form-label">表示順</label>
            <input type="number" name="sort_order" value="{{ old('sort_order') }}" class="form-control" min="1" max="99999">
          </div>
          <div class="col-md-4">
            <label class="form-label">大会内備考</label>
            <input type="text" name="source_note" value="{{ old('source_note') }}" class="form-control" maxlength="2000" placeholder="例: 公式レーン表より登録">
          </div>
          <div class="col-md-4">
            <label class="form-label">マスター備考</label>
            <input type="text" name="master_note" value="{{ old('master_note') }}" class="form-control" maxlength="2000" placeholder="次回以降の検索用メモ">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">アマチュア登録</button>
          </div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead>
            <tr>
              <th>表示順</th>
              <th>大会内No<br><span class="small text-muted">マスターNo</span></th>
              <th>氏名</th>
              <th>性別</th>
              <th>利き腕</th>
              <th>所属 / 用品契約</th>
              <th>開始位置</th>
              <th>備考</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            @php
              $amateurParticipantRows = $amateurParticipants ?? collect();
            @endphp
            @forelse ($amateurParticipantRows as $amateurParticipant)
              @php
                $amateurEditId = 'amateur-edit-row-' . $amateurParticipant->id;
                $amateurCurrentDominantArm = $amateurParticipant->display_dominant_arm ?? $amateurParticipant->master_dominant_arm ?? '';
                $amateurCurrentAffiliation = $amateurParticipant->display_affiliation_name ?? $amateurParticipant->master_affiliation_name ?? '';
                $amateurCurrentEquipment = $amateurParticipant->display_equipment_contract ?? $amateurParticipant->master_equipment_contract ?? '';
              @endphp
              <tr>
                <td>{{ $amateurParticipant->sort_order ?? '-' }}</td>
                <td>
                  {{ $amateurParticipant->display_license_no ?? 'アマ' }}<br>
                  <span class="text-muted small">{{ $amateurParticipant->pro_bowler_license_no }}</span>
                  @if (!empty($amateurParticipant->master_amateur_no))
                    <br><span class="badge text-bg-light">{{ $amateurParticipant->master_amateur_no }}</span>
                  @endif
                </td>
                <td>
                  <div class="fw-bold">{{ $amateurParticipant->display_name }}</div>
                  @if (!empty($amateurParticipant->master_name_kana))
                    <div class="text-muted small">{{ $amateurParticipant->master_name_kana }}</div>
                  @endif
                </td>
                <td>{{ $amateurParticipant->gender ?? '-' }}</td>
                <td>{{ $amateurCurrentDominantArm !== '' ? $amateurCurrentDominantArm : '-' }}</td>
                <td>
                  {{ $amateurCurrentAffiliation !== '' ? $amateurCurrentAffiliation : '-' }}
                  @if (!empty($amateurCurrentEquipment))
                    <br><span class="text-muted small">{{ $amateurCurrentEquipment }}</span>
                  @endif
                </td>
                <td>
                  {{ $amateurParticipant->lane_label ?? '-' }}
                  @if (!is_null($amateurParticipant->box_no))
                    <br><span class="text-muted small">BOX {{ $amateurParticipant->box_no }}</span>
                  @endif
                </td>
                <td class="small">{{ $amateurParticipant->source_note ?? '-' }}</td>
                <td>
                  <div class="d-flex gap-1 flex-wrap">
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            onclick="document.getElementById('{{ $amateurEditId }}').classList.toggle('d-none');">
                      編集
                    </button>
                    <form method="POST" action="{{ route('tournaments.amateur_participants.destroy', $amateurParticipant->id) }}" class="m-0">
                      @csrf
                      @method('DELETE')
                      <button type="submit"
                              class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('このアマチュア参加者を削除します。スコア入力済みの場合は削除できません。よろしいですか？');">
                        削除
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <tr id="{{ $amateurEditId }}" class="d-none">
                <td colspan="9" class="bg-light">
                  <form method="POST" action="{{ route('tournaments.amateur_participants.update', $amateurParticipant->id) }}" class="py-2">
                    @csrf
                    @method('PATCH')
                    <div class="row g-2 align-items-end">
                      <div class="col-md-3">
                        <label class="form-label small mb-1">アマチュア選手マスター</label>
                        <select name="amateur_bowler_id" class="form-select form-select-sm">
                          <option value="">マスター未選択 / 新規作成</option>
                          @foreach ($amateurBowlerOptions as $amateurBowler)
                            <option value="{{ $amateurBowler->id }}" {{ (string) $amateurParticipant->amateur_bowler_id === (string) $amateurBowler->id ? 'selected' : '' }}>
                              @if (!empty($amateurBowler->amateur_no))
                                {{ $amateurBowler->amateur_no }} /
                              @endif
                              {{ $amateurBowler->name }}
                              @if (!empty($amateurBowler->name_kana))
                                （{{ $amateurBowler->name_kana }}）
                              @endif
                              @if (!empty($amateurBowler->affiliation_name))
                                / {{ $amateurBowler->affiliation_name }}
                              @endif
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small mb-1">識別番号</label>
                        <input type="text" name="amateur_no" value="{{ $amateurParticipant->master_amateur_no }}" class="form-control form-control-sm" maxlength="32" placeholder="例: A000001">
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small mb-1">氏名</label>
                        <input type="text" name="name" value="{{ $amateurParticipant->display_name }}" class="form-control form-control-sm" maxlength="255" required>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small mb-1">フリガナ</label>
                        <input type="text" name="name_kana" value="{{ $amateurParticipant->master_name_kana }}" class="form-control form-control-sm" maxlength="255">
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">性別</label>
                        <select name="gender" class="form-select form-select-sm">
                          <option value="">未設定</option>
                          <option value="F" {{ ($amateurParticipant->gender ?? '') === 'F' ? 'selected' : '' }}>女子</option>
                          <option value="M" {{ ($amateurParticipant->gender ?? '') === 'M' ? 'selected' : '' }}>男子</option>
                          <option value="X" {{ ($amateurParticipant->gender ?? '') === 'X' ? 'selected' : '' }}>共通</option>
                        </select>
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">利き腕</label>
                        <select name="dominant_arm" class="form-select form-select-sm">
                          <option value="">未設定</option>
                          <option value="右" {{ $amateurCurrentDominantArm === '右' ? 'selected' : '' }}>右</option>
                          <option value="左" {{ $amateurCurrentDominantArm === '左' ? 'selected' : '' }}>左</option>
                          <option value="両" {{ $amateurCurrentDominantArm === '両' ? 'selected' : '' }}>両</option>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">所属ボウリング場</label>
                        <input type="text" name="affiliation_name" value="{{ $amateurCurrentAffiliation }}" class="form-control form-control-sm" maxlength="255">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">用品契約</label>
                        <input type="text" name="equipment_contract" value="{{ $amateurCurrentEquipment }}" class="form-control form-control-sm" maxlength="255">
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">開始L</label>
                        <input type="number" name="lane" value="{{ $amateurParticipant->lane }}" class="form-control form-control-sm" min="1" max="999">
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">枠</label>
                        <input type="number" name="lane_slot" value="{{ $amateurParticipant->lane_slot }}" class="form-control form-control-sm" min="1" max="9">
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">BOX</label>
                        <input type="number" name="box_no" value="{{ $amateurParticipant->box_no }}" class="form-control form-control-sm" min="1" max="999">
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">表示順</label>
                        <input type="number" name="sort_order" value="{{ $amateurParticipant->sort_order }}" class="form-control form-control-sm" min="1" max="99999">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">大会内備考</label>
                        <input type="text" name="source_note" value="{{ $amateurParticipant->source_note }}" class="form-control form-control-sm" maxlength="2000">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">マスター備考</label>
                        <input type="text" name="master_note" value="{{ $amateurParticipant->master_note }}" class="form-control form-control-sm" maxlength="2000">
                      </div>
                      <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill">保存</button>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                onclick="document.getElementById('{{ $amateurEditId }}').classList.add('d-none');">
                          閉じる
                        </button>
                      </div>
                    </div>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-muted text-center">アマチュア参加者はまだ登録されていません。</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
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
                <th>参加権利</th>
                <th>備考</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @php
                $priorityMissingEntries = $summary['priority_missing_entries'] ?? [];
              @endphp
              @foreach ($priorityMissingEntries as $priorityMissing)
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
                    <input type="text"
                           name="cancel_reason"
                           class="form-control form-control-sm mb-1"
                           maxlength="1000"
                           required
                           placeholder="取消理由">
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
