@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h2 class="mb-1">{{ $year }}年度 ボール登録申請</h2>
            <div class="text-muted">
                {{ $proBowler->name_kanji ?? '-' }}
                （{{ $proBowler->license_no ?? '-' }}）
                @if($staffProxy)
                    <span class="badge bg-warning text-dark ms-2">スタッフ代理入力</span>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($staffProxy)
                <a href="{{ route('ball_annual_registrations.index', ['year' => $year]) }}" class="btn btn-secondary">年度承認管理へ戻る</a>
            @else
                <a href="{{ route('member.dashboard') }}" class="btn btn-secondary">マイページへ戻る</a>
            @endif
            <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-secondary">マイボール管理</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>入力内容を確認してください。</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold">現在の申請</div>
                <div class="card-body">
                    @if($workingRegistration)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-{{ $workingRegistration->status_badge }}">{{ $workingRegistration->status_label }}</span>
                            <span>第{{ $workingRegistration->revision }}版</span>
                        </div>
                        <div class="small text-muted">選択 {{ $workingRegistration->usedBalls()->count() }}個</div>
                        @if($workingRegistration->status === \App\Models\BallAnnualRegistration::STATUS_RETURNED)
                            <div class="alert alert-danger mt-3 mb-0">
                                <div class="fw-bold">差戻し理由</div>
                                <div>{{ $workingRegistration->return_reason }}</div>
                            </div>
                        @elseif($workingRegistration->status === \App\Models\BallAnnualRegistration::STATUS_SUBMITTED)
                            <div class="alert alert-warning mt-3 mb-0">
                                スタッフの一括承認待ちです。承認または差戻しまで内容は変更できません。
                            </div>
                        @endif
                    @else
                        <span class="badge bg-secondary">未申請</span>
                        <div class="small text-muted mt-2">対象ボールを選び、年度申請を提出してください。</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold">大会登録に使える承認内容</div>
                <div class="card-body">
                    @if($latestApproved)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success">承認済み</span>
                            <span>第{{ $latestApproved->revision }}版 / {{ $latestApproved->usedBalls()->count() }}個</span>
                        </div>
                        <div class="small text-muted">
                            承認日時：{{ optional($latestApproved->approved_at)->format('Y-m-d H:i') ?? '-' }}
                        </div>
                        @if($workingRegistration)
                            <div class="small text-muted mt-2">新しい申請の審査中も、直前の承認内容は有効です。</div>
                        @endif
                    @else
                        <span class="badge bg-danger">承認なし</span>
                        <div class="small text-muted mt-2">承認されるまで、新しく大会使用ボールへ追加できません。</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border">
        <div class="fw-bold mb-1">年度申請と検量証は別管理です</div>
        <div class="small">
            年度申請は{{ $year }}年12月31日まで有効です。検量証は検量日から1年間有効で、年度をまたげます。
            追加・入れ替えがある場合は新しい版として申請し、承認後に大会登録へ反映されます。
        </div>
    </div>

    @if($usedBalls->isEmpty())
        <div class="alert alert-info">
            申請できるマイボールがありません。先にマイボール管理から登録してください。
        </div>
    @else
        <form method="POST" action="{{ route('ball_annual_registrations.draft') }}">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="pro_bowler_id" value="{{ $proBowler->id }}">

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:70px;">申請</th>
                            <th style="width:90px;">写真</th>
                            <th>メーカー / ボール名</th>
                            <th style="min-width:130px;">シリアル番号</th>
                            <th style="min-width:130px;">検量証状態</th>
                            <th style="min-width:150px;">アブプール照合</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usedBalls as $ball)
                            @php
                                $approvedBall = $ball->approvedBall;
                                $manufacturer = $approvedBall?->catalogManufacturer?->name
                                    ?? $approvedBall?->manufacturer
                                    ?? $approvedBall?->brand
                                    ?? '-';
                                $ballName = $approvedBall?->name ?? $approvedBall?->model_name ?? '-';
                                $isExpired = $ball->expires_at && $ball->expires_at->lt(today());
                                $isProvisional = blank($ball->inspection_number) || !$ball->expires_at;
                                $usbcMatched = ($approvedBall?->usbc_match_status ?? 'unmatched') === 'matched';
                                $checkedIds = old('used_ball_ids', $selectedIds);
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <input
                                        type="checkbox"
                                        name="used_ball_ids[]"
                                        value="{{ $ball->id }}"
                                        class="form-check-input"
                                        @checked(in_array((int) $ball->id, array_map('intval', $checkedIds), true))
                                        @disabled(!$canEdit)
                                    >
                                </td>
                                <td>
                                    @if($approvedBall)
                                        <img
                                            src="{{ route('approved_balls.image', $approvedBall->id) }}"
                                            alt="{{ $ballName }}"
                                            style="width:64px;height:64px;object-fit:contain;"
                                        >
                                    @endif
                                </td>
                                <td>
                                    <div class="small text-muted">{{ $manufacturer }}</div>
                                    <div class="fw-bold">{{ $ballName }}</div>
                                </td>
                                <td>{{ $ball->serial_number }}</td>
                                <td>
                                    @if($isProvisional)
                                        <span class="badge bg-warning text-dark">検量証待ち</span>
                                    @elseif($isExpired)
                                        <span class="badge bg-danger">期限切れ</span>
                                    @else
                                        <span class="badge bg-success">有効</span>
                                    @endif
                                </td>
                                <td>
                                    @if($usbcMatched)
                                        <span class="badge bg-success">掲載あり</span>
                                    @else
                                        <span class="badge bg-danger">掲載なし・要確認</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($canEdit)
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-primary">下書き保存</button>
                    <button
                        type="submit"
                        formaction="{{ route('ball_annual_registrations.submit') }}"
                        class="btn btn-primary"
                        onclick="return confirm('選択したボールを{{ $year }}年度分として提出しますか？')"
                    >
                        スタッフ承認へ提出
                    </button>
                </div>
            @endif
        </form>
    @endif

    @if($histories->isNotEmpty())
        <div class="card mt-4">
            <div class="card-header fw-bold">操作履歴</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>日時</th>
                            <th>版</th>
                            <th>操作</th>
                            <th>担当</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($histories as $history)
                            @php
                                $actionLabel = match($history->action) {
                                    'draft_saved' => '下書き保存',
                                    'submitted' => '提出',
                                    'approved' => '承認',
                                    'returned' => '差戻し',
                                    'superseded' => '旧版更新',
                                    default => $history->action,
                                };
                            @endphp
                            <tr>
                                <td>{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                <td>第{{ $history->registration?->revision }}版</td>
                                <td>{{ $actionLabel }}</td>
                                <td>{{ $history->actor?->name ?? 'システム' }}</td>
                                <td>{{ $history->note ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
