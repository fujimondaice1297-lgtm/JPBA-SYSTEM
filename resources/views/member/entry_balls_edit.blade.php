@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-3">大会使用ボール登録</h2>

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
        <div class="col-md-4">
          <div class="text-muted small">大会名</div>
          <div class="fw-bold">{{ $entry->tournament->name ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">現在登録数</div>
          <div class="fw-bold">{{ $existingCount }} / 12</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">追加可能数</div>
          <div class="fw-bold">{{ $remaining }}</div>
        </div>
        <div class="col-md-2">
          <div class="text-muted small">検量証必須</div>
          <div class="fw-bold">{{ $inspectionRequired ? '必須' : '任意' }}</div>
        </div>
      </div>

      <div class="mt-3 small text-muted">
        この画面では <strong>追加のみ</strong> 行います。すでに登録済みのボールは解除しません。<br>
        表示前に、登録ボールから大会使用ボールへの同期が自動で実行されます。
      </div>

      @if ($inspectionRequired)
        <div class="alert alert-warning mt-3 mb-0">
          この大会は検量証必須です。<br>
          <strong>「仮登録 / 検量証待ち」</strong> のボールは表示されますが、運用上は検量証番号の確認が必要です。
        </div>
      @endif
    </div>
  </div>

  <div class="d-flex gap-2 flex-wrap mb-3">
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">大会エントリー一覧へ戻る</a>
    <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-secondary">登録ボール管理</a>
    <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary">使用ボール管理</a>
  </div>

  @if ($usedBalls->isEmpty())
    <div class="alert alert-info">
      使用可能なボールがありません。<br>
      先に <strong>登録ボール管理</strong> または <strong>使用ボール管理</strong> でボールを登録してください。
    </div>
  @else
    <form method="POST" action="{{ route('member.entries.balls.store', $entry->id) }}">
      @csrf

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th style="width: 70px;">選択</th>
              <th>承認ボール</th>
              <th>シリアルNo</th>
              <th>検量証番号</th>
              <th>登録日</th>
              <th>有効期限</th>
              <th>状態</th>
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
                $isTemporary = is_null($usedBall->expires_at);
                $isExpired = !is_null($usedBall->expires_at) && $usedBall->expires_at->lt(now()->startOfDay());
                $disableNewSelect = (!$isLinked && $remaining <= 0);
              @endphp
              <tr>
                <td class="text-center">
                  @if ($isLinked)
                    <input type="checkbox" class="form-check-input" checked disabled>
                  @elseif ($disableNewSelect)
                    <input type="checkbox" class="form-check-input" disabled>
                  @else
                    <input
                      type="checkbox"
                      name="used_ball_ids[]"
                      value="{{ $usedBall->id }}"
                      class="form-check-input"
                      {{ in_array($usedBall->id, old('used_ball_ids', [])) ? 'checked' : '' }}
                    >
                  @endif
                </td>

                <td>
                  <div class="fw-bold">{{ $approvedBallName }}</div>
                  @if (data_get($usedBall, 'approvedBall.manufacturer'))
                    <div class="small text-muted">{{ data_get($usedBall, 'approvedBall.manufacturer') }}</div>
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
                  @if ($isLinked)
                    <span class="badge bg-success">登録済み</span>
                  @elseif ($isExpired)
                    <span class="badge bg-danger">期限切れ</span>
                  @elseif ($isTemporary)
                    <span class="badge bg-warning text-dark">仮登録 / 検量証待ち</span>
                  @else
                    <span class="badge bg-secondary">使用可能</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary" {{ $remaining <= 0 ? 'disabled' : '' }}>
          選択したボールを追加登録
        </button>
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">戻る</a>
      </div>

      @if ($remaining <= 0)
        <div class="alert alert-secondary mt-3 mb-0">
          すでに 12 個登録済みのため、これ以上追加できません。
        </div>
      @endif
    </form>
  @endif
</div>
@endsection