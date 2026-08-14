@extends('layouts.app')

@section('content')
@php
    // Controller から $user / $bowler が来てなくても自己完結するように保険
    $user   = $user   ?? auth()->user();
    $bowler = $bowler ?? $user?->proBowler ?? $user?->proBowlerByLicense;
@endphp

  <div class="d-flex align-items-center gap-2 mb-3">
    <h2 class="mb-0">会員ページ</h2>
    <span class="text-muted fs-6">（{{ $bowler?->license_no ?? 'N/A' }}）</span>
  </div>

    {{-- 操作ボタン --}}
  <div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">前のページへ</a>
    <a href="{{ route('member.dashboard') }}" class="btn btn-outline-primary">マイページトップ</a>

    {{-- ★ ロールごとの専用リンク --}}
    @if($user?->isAdmin())
      {{-- 管理者ダッシュボードのルート名は web.php で name('admin.home') にしているはず --}}
      <a href="{{ route('admin.home') }}" class="btn btn-danger">管理者画面へ</a>
    @endif

    {{-- editor 用ルートが未実装でもこけないように Route::has で守る --}}
    @if($user?->isEditor() && Route::has('editor.dashboard'))
      <a href="{{ route('editor.dashboard') }}" class="btn btn-warning">編集者画面へ</a>
    @endif

    {{-- ★ パスワード変更 --}}
    <a href="{{ route('password.change.form') }}" class="btn btn-warning">パスワードを変更</a>
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-success">大会エントリー・使用ボール登録</a>
    <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-success">マイボール管理</a>
    <a href="{{ ($user?->isAdmin() || $user?->isEditor())
        ? route('ball_annual_registrations.index')
        : route('ball_annual_registrations.edit') }}" class="btn btn-primary">
      {{ ($user?->isAdmin() || $user?->isEditor()) ? '年度ボール申請・承認' : '年度ボール申請' }}
    </a>

    @if($bowler?->id)
      <a href="{{ ($user?->isAdmin() || $user?->isEditor())
          ? route('pro_bowlers.edit', $bowler->id)
          : route('athlete.edit') }}" class="btn btn-outline-dark">
        プロフィール編集
      </a>
    @endif
  </div>

  {{-- ようこそ帯 --}}
  <p class="mb-4">{{ $bowler?->name ?? $user?->name }} さん、ようこそ。</p>

  @if($trainingCompliance)
    @php($trainingOk = (bool)($trainingCompliance['allowed'] ?? false))
    <div class="alert {{ $trainingOk ? 'alert-success' : 'alert-danger' }} d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <strong>トーナメントプレイヤー講習：{{ $trainingCompliance['label'] ?? '-' }}</strong>
        <div class="small">{{ $trainingCompliance['message'] ?? '' }}</div>
        @if($trainingCompliance['expires_at'] ?? null)<div class="small">有効期限：{{ $trainingCompliance['expires_at']->format('Y年n月j日') }}</div>@endif
      </div>
      @if(!$trainingOk)<span class="badge bg-danger fs-6">出場資格なし</span>@endif
    </div>
  @endif

  {{-- 2カラム：左に公開プロフィール、右にアカウント情報 --}}
  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">公開プロフィール（HP掲載の想定項目）</div>
        <div class="card-body">
          @if(!$bowler)
            <div class="alert alert-warning mb-0">
              プロボウラーデータが見つかりません。事務局にお問い合わせください。
            </div>
          @else
            @include('member._profile_public_summary', ['b' => $bowler])
          @endif
        </div>
      </div>
    </div>

    @if(($mypageGroups?->count() ?? 0) > 0)
      <div class="alert alert-info d-flex flex-column gap-1">
        <div class="fw-bold mb-1">あなたの該当グループ</div>
        @foreach($mypageGroups as $g)
          <div>・{{ $g->name }}</div>
        @endforeach
      </div>
    @endif

    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">アカウント情報</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-4">メール</dt>
            <dd class="col-8">{{ $user?->email }}</dd>

            {{-- 旧: $b->login_id は未定義。ログインIDは name か email のどちらかに寄せる --}}
            <dt class="col-4">ログインID</dt>
            <dd class="col-8">{{ $user?->name ?? $user?->email ?? '—' }}</dd>

            <dt class="col-4">ライセンスNo</dt>
            <dd class="col-8">{{ $user?->pro_bowler_license_no ?? $bowler?->license_no ?? '—' }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
@endsection
