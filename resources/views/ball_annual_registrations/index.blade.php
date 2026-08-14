@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h2 class="mb-1">年度ボール申請・一括承認</h2>
            <div class="text-muted">選手ごとに、その年度へ提出されたボールをまとめて承認します。</div>
        </div>
        <a href="{{ route('used_balls.index') }}" class="btn btn-secondary">選手登録ボール管理へ</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('ball_annual_registrations.index') }}" class="row g-2 mb-3">
        <div class="col-md-2">
            <label class="form-label">年度</label>
            <input type="number" name="year" value="{{ $year }}" min="2000" max="{{ now()->year + 1 }}" class="form-control">
        </div>
        <div class="col-md-7">
            <label class="form-label">選手検索</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="氏名・カナ・ライセンス番号" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">表示</button>
            <a href="{{ route('ball_annual_registrations.index', ['year' => now()->year]) }}" class="btn btn-outline-secondary">解除</a>
        </div>
    </form>

    <div class="alert alert-light border small">
        「承認」は1球ずつではなく、選手が提出した{{ $year }}年度分をまとめて確定します。
        後から追加申請が承認された場合は新しい版が正となり、直前の承認内容は履歴として残ります。
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>選手</th>
                    <th>マイボール</th>
                    <th>現在の申請</th>
                    <th>承認済み</th>
                    <th style="min-width:320px;">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bowlers as $bowler)
                    @php
                        $row = $registrationRows[(int) $bowler->id] ?? ['current' => null, 'approved' => null];
                        $current = $row['current'];
                        $approved = $row['approved'];
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $bowler->name_kanji }}</div>
                            <div class="small text-muted">{{ $bowler->license_no }}</div>
                        </td>
                        <td>{{ $bowler->used_balls_count }}個</td>
                        <td>
                            @if($current)
                                <span class="badge bg-{{ $current->status_badge }}">{{ $current->status_label }}</span>
                                <div class="small mt-1">第{{ $current->revision }}版 / {{ $current->used_balls_count }}個</div>
                                @if($current->status === \App\Models\BallAnnualRegistration::STATUS_RETURNED)
                                    <div class="small text-danger mt-1">{{ $current->return_reason }}</div>
                                @endif
                            @else
                                <span class="badge bg-secondary">未申請</span>
                            @endif
                        </td>
                        <td>
                            @if($approved)
                                <span class="badge bg-success">承認済み</span>
                                <div class="small mt-1">第{{ $approved->revision }}版 / {{ $approved->used_balls_count }}個</div>
                                <div class="small text-muted">{{ optional($approved->approved_at)->format('Y-m-d H:i') }}</div>
                            @else
                                <span class="text-muted">なし</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                <a
                                    href="{{ route('ball_annual_registrations.edit', ['year' => $year, 'pro_bowler_id' => $bowler->id]) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    内容確認・代理申請
                                </a>

                                @if($current?->status === \App\Models\BallAnnualRegistration::STATUS_SUBMITTED)
                                    <form method="POST" action="{{ route('ball_annual_registrations.approve', $current->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('この選手の{{ $year }}年度申請を一括承認しますか？')">
                                            一括承認
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if($current?->status === \App\Models\BallAnnualRegistration::STATUS_SUBMITTED)
                                <form method="POST" action="{{ route('ball_annual_registrations.return', $current->id) }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="text" name="return_reason" class="form-control form-control-sm" placeholder="差戻し理由" required>
                                    <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">差戻し</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">マイボール登録済みの選手が見つかりません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $bowlers->links() }}
</div>
@endsection
