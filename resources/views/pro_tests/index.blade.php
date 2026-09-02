@extends('layouts.app')

@section('content')
<style>
    .protest-admin { max-width: 1220px; margin: 0 auto; }
    .protest-admin-hero { padding: 24px 26px; border-radius: 18px; background: linear-gradient(130deg, #183b69, #176e78); color: #fff; }
    .protest-admin-hero h1 { margin: 0 0 7px; font-size: 1.75rem; font-weight: 800; }
    .protest-admin-hero p { margin: 0; color: rgba(255,255,255,.84); }
    .protest-flow { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin-top: 18px; }
    .protest-flow div { padding: 12px; border-radius: 11px; background: rgba(255,255,255,.12); font-size: .78rem; }
    .protest-flow b { display: block; margin-bottom: 4px; font-size: .72rem; opacity: .72; }
    .protest-panel { margin-top: 22px; padding: 22px; border: 1px solid #e1e6ed; border-radius: 16px; background: #fff; }
    .protest-panel h2 { margin: 0 0 16px; font-size: 1.2rem; font-weight: 800; }
    @media (max-width: 900px) { .protest-flow { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="protest-admin">
    <header class="protest-admin-hero">
        <h1>プロテスト運用</h1>
        <p>受験者と得点を内部管理し、確認済みの版だけを一般速報へ公開します。</p>
        <div class="protest-flow" aria-label="運用手順">
            <div><b>STEP 1</b>年度を作成</div>
            <div><b>STEP 2</b>実施日を登録</div>
            <div><b>STEP 3</b>受験者を取込</div>
            <div><b>STEP 4</b>得点を取込</div>
            <div><b>STEP 5</b>速報を公開</div>
            <div><b>STEP 6</b>合格者を公開</div>
        </div>
    </header>

    <section class="protest-panel">
        <h2>年度を新規作成</h2>
        <form method="POST" action="{{ route('pro_tests.store') }}" class="row g-3">
            @csrf
            <div class="col-md-2"><label class="form-label">年度</label><input class="form-control" type="number" name="year" value="{{ old('year', now()->year + 1) }}" required></div>
            <div class="col-md-5"><label class="form-label">名称</label><input class="form-control" name="name" value="{{ old('name', (now()->year + 1).'年度プロボウラー資格取得テスト') }}" required></div>
            <div class="col-md-2"><label class="form-label">開始日</label><input class="form-control" type="date" name="start_date" value="{{ old('start_date') }}"></div>
            <div class="col-md-2"><label class="form-label">終了日</label><input class="form-control" type="date" name="end_date" value="{{ old('end_date') }}"></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">作成</button></div>
        </form>
    </section>

    <section class="protest-panel">
        <h2>登録済み年度</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>年度</th><th>名称</th><th>状態</th><th>実施日</th><th>受験者</th><th></th></tr></thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $event->year }}</td>
                        <td>{{ $event->name }}</td>
                        <td><span class="badge {{ $event->status === 'final' ? 'bg-success' : ($event->status === 'live' ? 'bg-primary' : 'bg-secondary') }}">{{ ['draft' => '準備中', 'live' => '速報公開中', 'final' => '結果確定'][$event->status] ?? $event->status }}</span></td>
                        <td>{{ $event->sessions_count }}</td>
                        <td>{{ $event->candidates_count }}</td>
                        <td class="text-end"><a class="btn btn-outline-primary" href="{{ route('pro_tests.show', $event) }}">運用画面</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">まだ登録されていません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
