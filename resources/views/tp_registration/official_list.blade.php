@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <div class="small text-primary fw-bold">JPBA公式修了者一覧</div>
    <h1 class="h3 mb-1">{{ $officialList->title }}</h1>
    <div class="text-muted">資格判定期間 {{ $officialList->valid_from?->format('Y/m/d') }} ～ {{ $officialList->valid_through?->format('Y/m/d') }}</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-success" href="{{ route('tp_registration.official_lists.export', $officialList) }}">CSV出力</a>
    <a class="btn btn-outline-secondary" href="{{ route('tp_registration.index') }}">講習会管理へ戻る</a>
  </div>
</div>

<div class="alert alert-info">
  公式資料に掲載された実在受講者を出典順で保存しています。個別受講日が掲載されていないため、この一覧から日付単位の受講履歴は作成していません。
</div>

<section class="card shadow-sm border-0 mb-4"><div class="card-body">
  <form method="GET" action="{{ route('tp_registration.official_lists.show', $officialList) }}" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">性別</label><select name="gender" class="form-select"><option value="">男女すべて</option><option value="M" @selected($gender==='M')>男子</option><option value="F" @selected($gender==='F')>女子</option></select></div>
    <div class="col-md-6"><label class="form-label">氏名・ライセンスNo</label><input name="q" value="{{ $keyword }}" class="form-control"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill">検索</button><a class="btn btn-outline-secondary" href="{{ route('tp_registration.official_lists.show', $officialList) }}">解除</a></div>
  </form>
</div></section>

<section class="card shadow-sm border-0"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">受講者</h2><span class="text-muted">{{ number_format($entries->total()) }}名</span></div>
  <div class="table-responsive"><table class="table align-middle">
    <thead class="table-light"><tr><th>掲載順</th><th>性別</th><th>ライセンスNo</th><th>氏名</th><th>照合状態</th><th>選手ページ</th></tr></thead>
    <tbody>
      @forelse($entries as $entry)
        <tr>
          <td>{{ $entry->source_order }}</td>
          <td>{{ $entry->gender === 'M' ? '男子' : '女子' }}</td>
          <td>{{ $entry->bowler ? \App\Support\PublicLicenseNumber::format($entry->bowler->license_no) : str_pad((string)$entry->license_no_num, 4, '0', STR_PAD_LEFT) }}</td>
          <td class="fw-bold">{{ $entry->bowler?->name_kanji ?: $entry->source_name ?: '―' }}</td>
          <td><span class="badge {{ in_array($entry->match_status, ['matched','inactive'], true) ? 'text-bg-success' : 'text-bg-warning' }}">{{ $entry->match_status }}</span></td>
          <td>@if($entry->bowler)<a class="btn btn-sm btn-outline-primary" href="{{ route('pro_bowlers.edit', $entry->bowler) }}">確認</a>@else―@endif</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-muted">該当者はいません。</td></tr>
      @endforelse
    </tbody>
  </table></div>
  @if($entries->hasPages())<div>{{ $entries->links() }}</div>@endif
</div></section>
@endsection
