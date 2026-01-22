@extends('layouts.app')

@section('content')
  {{-- 上部ヘッダー：左=タイトル、右=操作ボタン群（高さ固定・折り返し可） --}}
  <div class="mb-3 d-grid gap-2" style="grid-template-columns: 1fr auto;">
    <div class="pe-3">
      <h1 class="mb-1 lh-sm">{{ $group->name }}</h1>
      <div class="text-muted">
        種別：{{ $group->type_label }}　
        保持：{{ $group->retention_label }}@if($group->expires_at)（〜{{ $group->expires_at->format('Y-m-d') }}）@endif
      </div>
    </div>

    <div class="d-flex flex-wrap justify-content-end align-items-start gap-2 text-nowrap">
      {{-- 戻る系 --}}
      <a class="btn btn-outline-secondary btn-sm align-self-start" href="{{ route('pro_groups.index') }}">グループ一覧に戻る</a>
      <a class="btn btn-outline-primary btn-sm align-self-start" href="{{ route('athlete.index') }}">インデックスへ戻る</a>
      @if(auth()->user()?->isAdmin() || auth()->user()?->isEditor())
        <a class="btn btn-outline-dark btn-sm align-self-start"
          href="{{ route('pro_groups.mail.create', $group) }}">メール</a>
      @endif

      {{-- 管理者だけ削除 --}}
      @if(auth()->user()?->isAdmin())
        <form method="POST" action="{{ route('admin.pro_groups.destroy',$group) }}" class="m-0 d-inline">
          @csrf @method('DELETE')
          <button class="btn btn-outline-danger btn-sm align-self-start" onclick="return confirm('本当に削除しますか？');">削除</button>
        </form>
      @endif

      {{-- ルール型のみ再計算 --}}
      @if($group->type==='rule')
        <form method="POST" action="{{ route('pro_groups.rebuild',$group) }}" class="m-0 d-inline">
          @csrf
          <button class="btn btn-warning btn-sm align-self-start">再計算</button>
        </form>
      @endif

      <a class="btn btn-outline-secondary btn-sm align-self-start" href="{{ route('pro_groups.export_csv',$group) }}">CSV</a>
      <a class="btn btn-primary btn-sm align-self-start" href="{{ route('pro_groups.edit',$group) }}">設定</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header fw-bold">メンバー（{{ $group->members->count() }}件）</div>
    <div class="card-body p-0">
      <table class="table mb-0 table-striped">
        <thead>
          <tr>
            <th>ライセンス</th>
            <th>氏名</th>
            <th>地区</th>
            <th>付与日</th>
            <th>有効期限</th>
          </tr>
        </thead>
        <tbody>
          @forelse($group->members as $b)
            <tr>
              <td>{{ $b->license_no }}</td>
              <td>{{ $b->name_kanji }}</td>
              <td>{{ $b->district?->label }}</td>
              <td>{{ optional($b->pivot->assigned_at)->format('Y-m-d H:i') }}</td>
              <td>{{ optional($b->pivot->expires_at)->format('Y-m-d') ?: '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">まだメンバーがいません。</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
