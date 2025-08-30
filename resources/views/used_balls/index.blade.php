{{-- resources/views/used_balls/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <h2>使用ボール 一覧 <small class="text-muted">(管理)</small></h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ナビボタン --}}
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ</a>
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
        <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary">承認ボール一覧へ</a>
    </div>

    {{-- 検索フォーム --}}
    <form method="GET" action="{{ route('used_balls.index') }}" class="mb-3">
        <div class="d-flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="名前・ライセンス番号で検索" class="form-control">
            <button type="submit" class="btn btn-primary">検索</button>
        </div>
    </form>

    <div class="mb-3">
        <a href="{{ route('used_balls.create') }}" class="btn btn-outline-primary">＋ 使用ボールを登録</a>
    </div>

    <table class="table align-middle">
        <thead>
            <tr>
                <th style="min-width:110px;">ライセンス番号</th>
                <th style="min-width:120px;">名前</th>
                <th>ボール名</th>
                <th style="min-width:120px;">シリアル番号</th>
                <th style="min-width:160px;">検量証番号 / 状態</th>
                <th style="min-width:110px;">登録日</th>
                <th style="min-width:110px;">有効期限</th>
                <th style="min-width:140px;">操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($usedBalls as $ball)
                <tr>
                    <td>{{ $ball->proBowler?->license_no ?? '未登録' }}</td>
                    <td>{{ $ball->proBowler?->name_kanji ?? '未登録' }}</td>
                    <td>{{ $ball->approvedBall?->name ?? '' }}</td>
                    <td>{{ $ball->serial_number }}</td>

                    {{-- 検量証／状態表示 --}}
                    <td>
                        @if($ball->inspection_number)
                            {{ $ball->inspection_number }}
                            <span class="badge bg-info ms-1">検量証OK</span>
                        @else
                            <span class="text-muted">（なし）</span>
                            <span class="badge bg-warning text-dark ms-1">仮登録</span>
                        @endif
                    </td>

                    <td>{{ optional($ball->registered_at)->format('Y-m-d') }}</td>

                    {{-- 有効期限（仮登録はダッシュ表示） --}}
                    <td>
                        @if($ball->expires_at)
                            {{ optional($ball->expires_at)->format('Y-m-d') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td class="d-flex gap-1">
                        <a href="{{ route('used_balls.edit', $ball->id) }}" class="btn btn-success btn-sm">更新</a>
                        <form action="{{ route('used_balls.destroy', $ball->id) }}" method="POST" onsubmit="return confirm('削除しますか？')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">データが見つかりませんでした。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex gap-2">
            <a href="{{ route('approved_balls.index') }}" class="btn btn-secondary">承認ボール一覧へ</a>
            <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary">大会一覧へ</a>
            <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
        </div>
        {{ $usedBalls->links() }}
    </div>
</div>
@endsection
