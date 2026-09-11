@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">大会テンプレート</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary">大会一覧</a>
      <a href="{{ route('tournament_templates.create') }}" class="btn btn-primary">テンプレートを作成</a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(auth()->user()?->role === 'admin')
    <section class="card border-primary mb-4">
      <div class="card-header bg-primary text-white">
        <strong>ジャパンオープン標準構成を作成</strong>
      </div>
      <div class="card-body">
        <p class="mb-3">
          大会総合案内、男女のチーム戦・ダブルス戦・シングルス戦・9Gオールエベンツ、
          マスターズ／クイーンズの計11競技を一括作成します。選手・スコアは複製しません。
        </p>
        <form method="POST" action="{{ route('tournament_templates.japan_open.store') }}" class="row g-3 align-items-end">
          @csrf
          <div class="col-sm-3 col-lg-2">
            <label class="form-label">年度</label>
            <input type="number" name="year" class="form-control" min="2000" max="2100"
                   value="{{ old('year', now()->year) }}" required>
          </div>
          <div class="col-sm-3 col-lg-2">
            <label class="form-label">開催回</label>
            <input type="number" name="edition_no" class="form-control" min="1" max="999"
                   value="{{ old('edition_no') }}" placeholder="任意">
          </div>
          <div class="col-sm-6 col-lg-4">
            <label class="form-label">大会名称</label>
            <input type="text" name="name" class="form-control" maxlength="255"
                   value="{{ old('name') }}" placeholder="空欄なら年度・開催回から自動作成">
          </div>
          <div class="col-sm-6 col-lg-2">
            <label class="form-label">開始日</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}">
          </div>
          <div class="col-sm-6 col-lg-2">
            <label class="form-label">終了日</label>
            <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
          </div>
          <div class="col-sm-6 col-lg-4">
            <label class="form-label">会場名</label>
            <input type="text" name="venue_name" class="form-control" maxlength="255" value="{{ old('venue_name') }}">
          </div>
          <div class="col-sm-6 col-lg-4">
            <label class="form-label">会場住所</label>
            <input type="text" name="venue_address" class="form-control" maxlength="255" value="{{ old('venue_address') }}">
          </div>
          <div class="col-sm-4 col-lg-2">
            <label class="form-label">登録ボール上限</label>
            <input type="number" name="ball_registration_limit" class="form-control" min="1" max="99"
                   value="{{ old('ball_registration_limit', 12) }}" required>
          </div>
          <div class="col-sm-8 col-lg-2">
            <button type="submit" class="btn btn-primary w-100">年度構成を作成</button>
          </div>
        </form>
        <div class="small text-muted mt-3">
          同じ年度へ再実行した場合は既存構成を更新し、二重作成しません。各競技は下書きで作成されます。
        </div>
      </div>
    </section>
  @endif

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>テンプレート名</th>
          <th>シリーズ</th>
          <th>最新版</th>
          <th>更新日</th>
          <th class="text-end">操作</th>
        </tr>
      </thead>
      <tbody>
        @forelse($templates as $template)
          @php $version = $template->latestPublishedVersion; @endphp
          <tr>
            <td>
              <strong>{{ $template->name }}</strong>
              @if($template->description)<div class="small text-muted">{{ $template->description }}</div>@endif
            </td>
            <td>{{ $template->series?->name ?? '単発・共通' }}</td>
            <td>{{ $version ? 'v'.$version->version : '-' }}</td>
            <td>{{ $version?->published_at?->format('Y/m/d H:i') ?? '-' }}</td>
            <td class="text-end">
              @if($version)
                <a href="{{ route('tournament_templates.apply', $version) }}" class="btn btn-success btn-sm">この設定で大会作成</a>
              @endif
              <a href="{{ route('tournament_templates.create', ['tournament_template_id' => $template->id]) }}" class="btn btn-outline-primary btn-sm">新版を追加</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">登録済みテンプレートはありません。</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
