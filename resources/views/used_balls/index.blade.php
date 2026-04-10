
{{-- resources/views/used_balls/index.blade.php --}}
@extends('layouts.app')

@section('content')
@php $viewer = auth()->user(); @endphp
<div class="container">
    <h2>
        使用ボール 一覧
        <small class="text-muted">
            ({{ ($viewer?->isAdmin() || $viewer?->isEditor()) ? '全件（管理）' : '自分のボール' }})
        </small>
    </h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-secondary">登録ボール一覧へ</a>
        <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ</a>
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
        <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary">承認ボール一覧へ</a>
    </div>

    <form method="GET" action="{{ route('used_balls.index') }}" class="row g-2 mb-3">
        <div class="col-md-6">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="氏名・ライセンス番号・シリアル番号・検量証番号・ボール名で検索"
                class="form-control"
            >
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">状態で絞り込み</option>
                <option value="valid" @selected(request('status') === 'valid')>有効</option>
                <option value="expiring_soon" @selected(request('status') === 'expiring_soon')>期限間近</option>
                <option value="expired" @selected(request('status') === 'expired')>期限切れ</option>
                <option value="provisional" @selected(request('status') === 'provisional')>仮登録 / 検量証待ち</option>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="{{ route('used_balls.index') }}" class="btn btn-warning">リセット</a>
        </div>
    </form>

    <div class="mb-3">
        <a href="{{ route('used_balls.create') }}" class="btn btn-outline-primary">＋ 使用ボールを登録</a>
    </div>

    <table class="table align-middle table-bordered">
        <thead>
            <tr>
                <th style="min-width:110px;">ライセンス番号</th>
                <th style="min-width:120px;">名前</th>
                <th style="min-width:140px;">メーカー</th>
                <th>ボール名</th>
                <th style="min-width:120px;">シリアル番号</th>
                <th style="min-width:170px;">検量証番号</th>
                <th style="min-width:110px;">登録日</th>
                <th style="min-width:120px;">有効期限</th>
                <th style="min-width:150px;">状態</th>
                <th style="min-width:180px;">修正導線</th>
                <th style="min-width:140px;">操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($usedBalls as $ball)
                @php
                    $inspectionNumber = trim((string) ($ball->inspection_number ?? ''));
                    $isProvisional = $inspectionNumber === '' || is_null($ball->expires_at);
                    $isExpired = !$isProvisional && $ball->expires_at && $ball->expires_at->lt(today());
                    $isExpiringSoon = !$isProvisional && !$isExpired && $ball->expires_at && $ball->expires_at->lte(today()->copy()->addDays(30));
                    $statusLabel = $isProvisional ? '仮登録 / 検量証待ち' : ($isExpired ? '期限切れ' : ($isExpiringSoon ? '期限間近' : '有効'));
                    $statusClass = $isProvisional ? 'warning text-dark' : ($isExpired ? 'danger' : ($isExpiringSoon ? 'warning text-dark' : 'success'));
                    $actionLabel = $isProvisional ? '検量証登録' : ($isExpired ? '再検量更新' : '更新');
                @endphp
                <tr>
                    <td>{{ $ball->proBowler?->license_no ?? '未登録' }}</td>
                    <td>{{ $ball->proBowler?->name_kanji ?? '未登録' }}</td>
                    <td>{{ $ball->approvedBall?->manufacturer ?? $ball->approvedBall?->brand ?? '―' }}</td>
                    <td>{{ $ball->approvedBall?->name ?? $ball->approvedBall?->model_name ?? '' }}</td>
                    <td>{{ $ball->serial_number }}</td>

                    <td>
                        @if($inspectionNumber !== '')
                            <div>{{ $inspectionNumber }}</div>
                        @else
                            <span class="text-muted">（なし）</span>
                        @endif
                    </td>

                    <td>{{ optional($ball->registered_at)->format('Y-m-d') }}</td>

                    <td>
                        @if($ball->expires_at)
                            <div>{{ optional($ball->expires_at)->format('Y-m-d') }}</div>
                            @if(!$isProvisional)
                                <small class="text-muted">
                                    @if($isExpired)
                                        {{ abs(today()->diffInDays($ball->expires_at, false)) }}日経過
                                    @else
                                        残り{{ today()->diffInDays($ball->expires_at, false) }}日
                                    @endif
                                </small>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td>
                        <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>

                    <td>
                        @if($isProvisional)
                            <span class="text-muted">検量証番号を登録してください</span>
                        @elseif($isExpired)
                            <span class="text-muted">再検量後に更新してください</span>
                        @elseif($isExpiringSoon)
                            <span class="text-muted">期限前に更新を推奨</span>
                        @else
                            <span class="text-muted">このまま使用可能です</span>
                        @endif
                    </td>

                    <td class="d-flex gap-1 flex-wrap">
                        <a href="{{ route('used_balls.edit', $ball->id) }}" class="btn btn-success btn-sm">{{ $actionLabel }}</a>

                        @if(auth()->user()?->isAdmin())
                            <form action="{{ route('admin.used_balls.destroy', $ball->id) }}"
                                  method="POST" onsubmit="return confirm('削除しますか？')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">削除</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-muted">データが見つかりませんでした。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('registered_balls.index') }}" class="btn btn-secondary">登録ボール一覧へ</a>
            <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary">承認ボール一覧へ</a>
            <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
        </div>
        {{ $usedBalls->links() }}
    </div>
</div>
@endsection
