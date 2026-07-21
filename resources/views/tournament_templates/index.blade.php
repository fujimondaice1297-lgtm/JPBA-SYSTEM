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
