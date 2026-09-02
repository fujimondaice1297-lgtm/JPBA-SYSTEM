@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1040px;">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1">選手アカウント管理</h1>
      <p class="text-muted mb-0">{{ $bowler->name_kanji ?: $bowler->name_kana }} / {{ \App\Support\PublicLicenseNumber::format($bowler->license_no) }}</p>
    </div>
    <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="btn btn-outline-secondary">選手編集へ戻る</a>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>アカウント状態</strong>
      <span class="badge {{ !$account ? 'bg-secondary' : ($account->isAccountActive() ? 'bg-success' : 'bg-warning text-dark') }}">
        {{ $account?->account_status_label ?? '未発行' }}
      </span>
    </div>
    <div class="card-body">
      @if (!$account)
        <p>有効なプロフィールメールを確認した後、個別にアカウントを発行してください。共通初期パスワードは作りません。</p>
        <dl class="row mb-3">
          <dt class="col-sm-3">登録メール</dt><dd class="col-sm-9">{{ $bowler->email ?: '未登録' }}</dd>
          <dt class="col-sm-3">会員状態</dt><dd class="col-sm-9">{{ $bowler->is_active ? '有効' : '無効（発行不可）' }}</dd>
        </dl>
        <form method="POST" action="{{ route('admin.player_accounts.issue', $bowler) }}" onsubmit="return confirm('本人確認済みのメールアドレスですか？ 選手アカウントを発行します。');">
          @csrf
          <button class="btn btn-primary" @disabled(!$bowler->is_active || !filter_var($bowler->email, FILTER_VALIDATE_EMAIL))>この選手へ発行</button>
        </form>
      @else
        <div class="row g-3 mb-4">
          <div class="col-md-4"><small class="text-muted d-block">ログインID</small>{{ $account->pro_bowler_license_no ?: $account->license_no }}</div>
          <div class="col-md-5"><small class="text-muted d-block">メール</small>{{ $account->email }}</div>
          <div class="col-md-3"><small class="text-muted d-block">権限</small>{{ $account->role }}</div>
          <div class="col-md-4"><small class="text-muted d-block">初期設定メール</small>{{ $account->setup_link_sent_at?->format('Y-m-d H:i') ?? '未送信' }}</div>
          <div class="col-md-4"><small class="text-muted d-block">パスワード設定</small>{{ $account->password_set_at?->format('Y-m-d H:i') ?? '未確認' }}</div>
          <div class="col-md-4"><small class="text-muted d-block">選手ID結線</small>{{ (int) $account->pro_bowler_id === (int) $bowler->id ? '正常' : '要確認' }}</div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
          <form method="POST" action="{{ route('admin.player_accounts.issue', $bowler) }}" onsubmit="return confirm('選手プロフィールの氏名・メール・ライセンス番号をアカウントへ同期します。');">
            @csrf
            <button class="btn btn-outline-secondary">プロフィール情報を同期</button>
          </form>
          <form method="POST" action="{{ route('admin.player_accounts.setup_link', $bowler) }}" onsubmit="return confirm('登録メールへパスワード設定リンクを送信します。');">
            @csrf
            <button class="btn btn-primary" @disabled(!$account->isAccountActive())>初回設定／再設定メールを送信</button>
          </form>
        </div>

        <form method="POST" action="{{ route('admin.player_accounts.status', $bowler) }}" class="border rounded p-3 mb-4">
          @csrf
          <h2 class="h6">利用状態を変更</h2>
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">状態</label>
              <select name="account_status" class="form-select">
                <option value="active" @selected(old('account_status', $account->account_status) === 'active')>利用中</option>
                <option value="suspended" @selected(old('account_status', $account->account_status) === 'suspended')>利用停止</option>
                <option value="closed" @selected(old('account_status', $account->account_status) === 'closed')>終了</option>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label">理由・メモ</label>
              <input type="text" name="reason" class="form-control" maxlength="1000" value="{{ old('reason', $account->account_status_note) }}" placeholder="停止・終了時は必須">
            </div>
            <div class="col-md-2"><button class="btn btn-outline-danger w-100">変更</button></div>
          </div>
          <div class="form-text">利用停止・終了では既存セッションと未使用の再設定トークンを破棄します。大会・ボール等の履歴は削除しません。</div>
        </form>

        <h2 class="h5">状態変更履歴</h2>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>日時</th><th>変更</th><th>理由</th><th>担当</th></tr></thead>
            <tbody>
              @forelse ($account->accountStatusLogs as $log)
                <tr>
                  <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                  <td>{{ $log->from_status ?: '未発行' }} → {{ $log->to_status }}</td>
                  <td>{{ $log->reason ?: '-' }}</td>
                  <td>{{ $log->changedByUser?->name ?: 'システム' }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-muted">履歴はありません。</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
