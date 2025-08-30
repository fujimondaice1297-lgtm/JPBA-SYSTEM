@extends('layouts.app')

@section('content')
  <div class="d-flex align-items-center gap-2 mb-3">
    <h2 class="mb-0">会員ページ</h2>
    <span class="text-muted fs-6">（{{ $user->proBowler->license_no ?? 'N/A' }}）</span>
  </div>

  {{-- 操作ボタン --}}
  <div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">前のページへ</a>
    <a href="{{ route('athlete.index') }}" class="btn btn-outline-primary">インデックスへ戻る</a>

    {{-- ★ ここが目的のパスワード変更ボタン --}}
    <a href="{{ route('password.change.form') }}" class="btn btn-warning">パスワードを変更</a>

    @if($bowler?->id)
      {{-- 管理側の編集画面が許可されていれば表示（権限はRoute側で制御） --}}
      <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="btn btn-outline-dark">
        プロフィール編集
      </a>
    @endif
  </div>

  {{-- ようこそ帯 --}}
  <p class="mb-4">{{ $user->proBowler->name ?? $user->name }} さん、ようこそ。</p>

  {{-- 2カラム：左に公開プロフィール、右に連絡など（必要なら拡張） --}}
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

    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">アカウント情報</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-4">メール</dt>
            <dd class="col-8">{{ $user->email }}</dd>

            <dt class="col-4">ログインID</dt>
            <dd class="col-8">{{ $b->login_id ?? '—' }}</dd>

            <dt class="col-4">ライセンスNo</dt>
            <dd class="col-8">{{ $user->pro_bowler_license_no ?? '—' }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
@endsection
