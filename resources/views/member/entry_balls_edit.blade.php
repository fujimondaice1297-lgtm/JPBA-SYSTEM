@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-3">
    大会使用ボール登録
    @if ($staffProxy)
      <span class="badge bg-warning text-dark fs-6">スタッフ代理入力</span>
    @endif
  </h2>

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

  <div class="card mb-4">
    <div class="card-header fw-bold">対象大会 / 登録状況</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted small">大会名</div>
          <div class="fw-bold">{{ $entry->tournament->name ?? '-' }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">選手</div>
          <div class="fw-bold">{{ optional($entry->bowler)->name_kanji ?? '-' }}</div>
          <div class="small text-muted">{{ $entryLicenseNo ?? '-' }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">現在登録数</div>
          <div class="fw-bold">{{ $existingCount }} / {{ $ballLimit }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">追加可能数</div>
          <div class="fw-bold">{{ $remaining }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">候補数</div>
          <div class="fw-bold">{{ $summary['total'] ?? 0 }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">検量証必須</div>
          <div class="fw-bold">{{ $inspectionRequired ? '必須' : '任意' }}</div>
          @if($inspectionRequired)
            <div class="small text-muted">判定日 {{ $inspectionReferenceDate->format('Y-m-d') }}</div>
          @endif
        </div>
      </div>

      <div class="row g-2 mt-3 small">
        <div class="col-md-3">登録済み: <strong>{{ $summary['linked'] ?? 0 }}</strong></div>
        <div class="col-md-3">未登録候補: <strong>{{ $summary['available'] ?? 0 }}</strong></div>
        <div class="col-md-3">仮登録 / 検量証待ち: <strong>{{ $summary['provisional'] ?? 0 }}</strong></div>
        <div class="col-md-3">有効: <strong>{{ $summary['valid'] ?? 0 }}</strong></div>
        <div class="col-md-3">期限間近: <strong>{{ $summary['expiring_soon'] ?? 0 }}</strong></div>
        <div class="col-md-3">期限切れ: <strong>{{ $summary['expired'] ?? 0 }}</strong></div>
        @if($inspectionRequired)
          <div class="col-md-3">大会使用不可: <strong>{{ $summary['tournament_ineligible'] ?? 0 }}</strong></div>
        @endif
      </div>

      <div class="mt-3 small text-muted">
        チェックを付けたボールを大会使用ボールとして登録し、チェックを外したボールは大会から解除します。<br>
        表示前に、登録ボールから大会使用ボールへの同期が自動で実行されます。<br>
        <strong>検量証情報を更新した場合は、この画面へ戻ると最新状態が反映されます。</strong>
      </div>

      @if ($inspectionRequired)
        <div class="alert alert-danger mt-3 mb-0">
          この大会は検量証必須です。大会開催日（{{ $inspectionReferenceDate->format('Y-m-d') }}）時点で有効な検量証がないボールは新しく選択できません。<br>
          既に登録済みの履歴は保持しますが、一度解除すると検量証更新後まで再追加できません。
        </div>
      @endif
    </div>
  </div>

  <div class="alert {{ $approvedAnnualRegistration ? 'alert-success' : 'alert-warning' }}">
    <div class="fw-bold">
      {{ $registrationYear }}年度ボール申請：
      {{ $approvedAnnualRegistration ? '承認済み' : '未承認' }}
    </div>
    <div class="small mt-1">
      @if($approvedAnnualRegistration)
        承認済み{{ count($approvedAnnualBallIds) }}個から大会使用ボールを選択できます。
      @else
        新しいボールを大会へ追加するには、先に年度申請を提出し、スタッフの一括承認を受けてください。
      @endif
    </div>
  </div>

  <div class="d-flex gap-2 flex-wrap mb-3">
    @if ($staffProxy)
      <a href="{{ route('tournaments.entries.index', $entry->tournament_id) }}" class="btn btn-secondary">大会の選手一覧へ戻る</a>
    @else
      <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">大会エントリー一覧へ戻る</a>
    @endif
    <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-secondary">登録ボール管理</a>
    <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary">使用ボール管理</a>
    <a href="{{ route('ball_annual_registrations.edit', ['year' => $registrationYear, 'pro_bowler_id' => $entry->pro_bowler_id]) }}" class="btn btn-primary">
      {{ $registrationYear }}年度申請を確認
    </a>
    <a href="{{ route('registered_balls.create', ['license_no' => $entryLicenseNo, 'return_to' => 'entry_balls', 'entry_id' => $entry->id]) }}" class="btn btn-outline-primary">本登録を追加</a>
    <a href="{{ route('used_balls.create', ['license_no' => $entryLicenseNo, 'return_to' => 'entry_balls', 'entry_id' => $entry->id]) }}" class="btn btn-outline-primary">仮登録を追加</a>
  </div>

  @if ($usedBalls->isEmpty())
    <div class="alert alert-info">
      大会使用ボールの候補がありません。<br>
      先にボールを登録し、<strong>{{ $registrationYear }}年度ボール申請</strong>のスタッフ承認を受けてください。
    </div>
  @else
    <form method="POST" action="{{ route('member.entries.balls.store', $entry->id) }}">
      @csrf

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th style="width: 70px;">選択</th>
              <th>登録ボール</th>
              <th>シリアルNo</th>
              <th>検量証番号</th>
              <th>検量日／登録日</th>
              <th>有効期限</th>
              <th>状態</th>
              <th style="min-width: 220px;">修正導線</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($usedBalls as $usedBall)
              @php
                $approvedBallName =
                    data_get($usedBall, 'approvedBall.name_ja')
                    ?? data_get($usedBall, 'approvedBall.name')
                    ?? data_get($usedBall, 'approvedBall.model_name')
                    ?? data_get($usedBall, 'approvedBall.ball_name')
                    ?? ('承認ボールID: ' . ($usedBall->approved_ball_id ?? '-'));

                $isLinked = in_array($usedBall->id, $linkedIds ?? [], true);
                $isAnnualApproved = in_array((int) $usedBall->id, array_map('intval', $approvedAnnualBallIds ?? []), true);
                $inspectionMeta = $inspectionStatuses[(int) $usedBall->id] ?? [];
                $currentInspectionStatus = $inspectionMeta['current'] ?? ['key' => 'provisional'];
                $tournamentInspection = $inspectionMeta['tournament'] ?? ['allowed' => false, 'message' => '検量証情報を確認できません。'];
                $isTemporary = ($currentInspectionStatus['key'] ?? '') === 'provisional';
                $isExpired = ($currentInspectionStatus['key'] ?? '') === 'expired';
                $isExpiringSoon = ($currentInspectionStatus['key'] ?? '') === 'expiring_soon';
                $isTournamentEligible = (bool) ($tournamentInspection['allowed'] ?? false);
                $disableNewSelect = !$isLinked
                    && (!$isAnnualApproved || ($inspectionRequired && !$isTournamentEligible));

                $registeredPrefill = [
                    'license_no' => $entryLicenseNo,
                    'approved_ball_id' => optional($usedBall->approvedBall)->id,
                    'serial_number' => $usedBall->serial_number,
                    'registered_at' => optional($usedBall->registered_at)->format('Y-m-d'),
                    'return_to' => 'entry_balls',
                    'entry_id' => $entry->id,
                ];
              @endphp
              <tr>
                <td class="text-center">
                  @if ($disableNewSelect)
                    <input type="checkbox" class="form-check-input" disabled>
                  @else
                    <input
                      type="checkbox"
                      name="used_ball_ids[]"
                      value="{{ $usedBall->id }}"
                      class="form-check-input"
                      {{ in_array($usedBall->id, old('used_ball_ids', $linkedIds ?? [])) ? 'checked' : '' }}
                    >
                  @endif
                </td>

                <td>
                  <div class="fw-bold">{{ $approvedBallName }}</div>
                  @if (data_get($usedBall, 'approvedBall.manufacturer'))
                    <div class="small text-muted">{{ data_get($usedBall, 'approvedBall.manufacturer') }}</div>
                  @endif
                  @if($isAnnualApproved)
                    <span class="badge bg-success mt-1">{{ $registrationYear }}年度承認済み</span>
                  @elseif($isLinked)
                    <span class="badge bg-secondary mt-1">既存登録（年度承認前）</span>
                  @else
                    <span class="badge bg-warning text-dark mt-1">年度承認が必要</span>
                  @endif
                </td>

                <td>{{ $usedBall->serial_number ?? '-' }}</td>

                <td>
                  @if (!empty($usedBall->inspection_number))
                    {{ $usedBall->inspection_number }}
                  @else
                    <span class="text-muted">未登録</span>
                  @endif
                </td>

                <td>{{ optional($usedBall->registered_at)->format('Y-m-d') ?? '-' }}</td>

                <td>
                  @if ($isTemporary)
                    <span class="text-muted">未設定</span>
                  @else
                    {{ optional($usedBall->expires_at)->format('Y-m-d') ?? '-' }}
                  @endif
                </td>

                <td>
                  @if ($isLinked && $inspectionRequired && !$isTournamentEligible)
                    <span class="badge bg-danger">登録済み / 大会時点で使用不可</span>
                  @elseif ($isLinked && $isExpiringSoon)
                    <span class="badge bg-warning text-dark">登録済み / 期限間近</span>
                  @elseif ($isLinked)
                    <span class="badge bg-success">登録済み</span>
                  @elseif ($inspectionRequired && !$isTournamentEligible)
                    <span class="badge bg-danger">大会使用不可</span>
                  @elseif ($isExpired)
                    <span class="badge bg-danger">期限切れ</span>
                  @elseif ($isTemporary)
                    <span class="badge bg-warning text-dark">仮登録 / 検量証待ち</span>
                  @elseif ($isExpiringSoon)
                    <span class="badge bg-warning text-dark">期限間近</span>
                  @else
                    <span class="badge bg-secondary">使用可能</span>
                  @endif
                  @if($inspectionRequired && !$isTournamentEligible)
                    <div class="small text-danger mt-1">{{ $tournamentInspection['message'] ?? '' }}</div>
                  @endif
                </td>

                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    @if ($isTemporary)
                      <a href="{{ route('used_balls.edit', ['used_ball' => $usedBall->id, 'return_to' => 'entry_balls', 'entry_id' => $entry->id]) }}" class="btn btn-sm btn-outline-primary">検量証登録</a>
                      <a href="{{ route('registered_balls.create', $registeredPrefill) }}" class="btn btn-sm btn-primary">本登録へ</a>
                    @elseif ($isExpired || $isExpiringSoon || ($inspectionRequired && !$isTournamentEligible))
                      <a href="{{ route('used_balls.edit', ['used_ball' => $usedBall->id, 'return_to' => 'entry_balls', 'entry_id' => $entry->id]) }}" class="btn btn-sm btn-outline-danger">再検量更新</a>
                      @if($isExpired)
                        <a href="{{ route('registered_balls.create', $registeredPrefill) }}" class="btn btn-sm btn-outline-primary">本登録を作り直す</a>
                      @endif
                    @else
                      <a href="{{ route('used_balls.edit', ['used_ball' => $usedBall->id, 'return_to' => 'entry_balls', 'entry_id' => $entry->id]) }}" class="btn btn-sm btn-outline-secondary">状態確認</a>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary">
          選択内容を保存
        </button>
        @if ($staffProxy)
          <a href="{{ route('tournaments.entries.index', $entry->tournament_id) }}" class="btn btn-secondary">戻る</a>
        @else
          <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">戻る</a>
        @endif
      </div>

      @if ($remaining <= 0)
        <div class="alert alert-secondary mt-3 mb-0">
          すでに {{ $ballLimit }} 個登録済みです。追加する場合は、先に別のボールのチェックを外して入れ替えてください。
        </div>
      @endif
    </form>
  @endif
</div>
@endsection
