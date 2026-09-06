@extends('layouts.app')
@section('title', '大会アーカイブ管理')
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3"><div><h1 class="h3 fw-bold mb-1">大会アーカイブ管理</h1><div class="text-muted">2016年以降の保存済み大会ページを編集します。</div></div><a href="{{ route('public.tournament_archives.index') }}" target="_blank" class="btn btn-outline-primary">一般公開を確認</a></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-dark"><tr><th>年度</th><th>開催日</th><th>大会名</th><th>資料</th><th>公開</th><th>操作</th></tr></thead><tbody>@foreach($archives as $archive)<tr><td>{{ $archive->year }}</td><td>{{ $archive->start_on?->format('Y-m-d') ?: '-' }}</td><td>{{ $archive->title }}</td><td>{{ count($archive->assets ?: []) }}</td><td>{{ $archive->is_public ? '公開' : '非公開' }}</td><td><a href="{{ route('admin.tournament_archives.edit', $archive) }}" class="btn btn-sm btn-primary">編集</a></td></tr>@endforeach</tbody></table></div></div>
<div class="mt-3">{{ $archives->links() }}</div>
@endsection
