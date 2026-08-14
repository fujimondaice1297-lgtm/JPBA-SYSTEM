@extends('layouts.app')

@section('content')
@if($isPublic)
<style>
    header, nav, .navbar, .topbar, .site-header, .app-header,
    .sidebar, .breadcrumb, .admin-menu, .auth-status, .login-state,
    .global-nav, .main-nav, .pwa-header, .layout-header {
        display: none !important;
        visibility: hidden !important;
    }
    body { padding-top: 0 !important; }
</style>
@endif

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h2 class="mb-1">大会登録ボール</h2>
            <div class="text-muted">{{ $entry->tournament?->name ?? '大会名未設定' }}</div>
        </div>
        <a href="{{ $returnUrl }}" class="btn btn-outline-secondary">速報へ戻る</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-3">
                        @if($portraitUrl)
                            <img
                                src="{{ $portraitUrl }}"
                                alt="{{ $entry->bowler?->name_kanji ?? '選手' }}"
                                style="width:64px; height:64px; border-radius:50%; object-fit:cover; object-position:center; background:#f3f4f6; border:1px solid #e5e7eb; flex:0 0 auto;"
                            >
                        @endif
                        <div>
                            <div class="text-muted small">選手</div>
                            <div class="fs-4 fw-bold">{{ $entry->bowler?->name_kanji ?? '選手名未設定' }}</div>
                            <div class="text-muted">{{ $entry->bowler?->license_no ?? 'ライセンス番号未設定' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">大会登録数</div>
                    <div class="fs-4 fw-bold">{{ number_format($balls->count()) }}個</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">
                        この大会のエントリーに紐づけて登録されたボールのみ表示しています。
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($balls->isEmpty())
        <div class="alert alert-secondary">
            この選手は、この大会で使用するボールをまだ登録していません。
        </div>
    @else
        <div class="row g-3">
            @foreach($balls as $ball)
                @php
                    $catalogBall = $ball->approvedBall;
                    $ballName = trim((string) ($catalogBall?->name ?? '')) ?: ('ボールID ' . ($ball->approved_ball_id ?? '-'));
                    $manufacturer = trim((string) (
                        $catalogBall?->catalogManufacturer?->name
                        ?? $catalogBall?->manufacturer
                        ?? ''
                    ));
                    $brand = trim((string) ($catalogBall?->brand ?? ''));
                @endphp
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-4 col-md-2 text-center">
                                    <img
                                        src="{{ $catalogBall?->image_url ?? asset('images/ball-no-image.svg') }}"
                                        alt="{{ $ballName }}"
                                        style="width:100%; max-width:110px; aspect-ratio:1/1; object-fit:contain; border-radius:.5rem; background:#f8f9fa;"
                                    >
                                </div>
                                <div class="col-8 col-md-8">
                                    <div class="fw-bold fs-5">{{ $ballName }}</div>
                                    @if($manufacturer !== '' || $brand !== '')
                                        <div class="text-muted small">
                                            {{ implode(' / ', array_values(array_filter([$manufacturer, $brand], fn ($value) => $value !== ''))) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-2">
                                    <span class="badge bg-success">大会登録済み</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-4">
        <a href="{{ $returnUrl }}" class="btn btn-outline-secondary">速報へ戻る</a>
    </div>
</div>
@endsection
