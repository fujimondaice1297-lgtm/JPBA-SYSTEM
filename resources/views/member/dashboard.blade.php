@extends('layouts.app')

@section('content')
@php
    // Controller から $user / $bowler が来てなくても自己完結するように保険
    $user   = $user   ?? auth()->user();
    $bowler = $bowler ?? $user?->proBowler;
@endphp

  <div class="d-flex align-items-center gap-2 mb-3">
    <h2 class="mb-0">会員ページ</h2>
    <span class="text-muted fs-6">（{{ $bowler?->license_no ?? 'N/A' }}）</span>
  </div>

  {{-- 操作ボタン --}}
  <div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">前のページへ</a>
    <a href="{{ route('athlete.index') }}" class="btn btn-outline-primary">インデックスへ戻る</a>

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

    @if($bowler?->id)
      {{-- 管理側の編集画面（権限はRoute/Policy側で制御） --}}
      <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="btn btn-outline-dark">
        プロフィール編集
      </a>
    @endif
  </div>

  {{-- ようこそ帯 --}}
  <p class="mb-4">{{ $bowler?->name ?? $user?->name }} さん、ようこそ。</p>

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
