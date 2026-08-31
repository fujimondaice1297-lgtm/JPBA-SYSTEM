@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1">外部速報リンク管理</h1>
            <div class="text-muted small">通常の速報・全成績は大会のスコア入力から自動公開されます。ここでは特設サイト等の外部URLだけを登録します。</div>
        </div>
        <a href="{{ route('public.tournaments.live_results') }}" class="btn btn-outline-primary" target="_blank">一般公開ページを確認</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <a href="{{ route('flash_news.create') }}" class="btn btn-primary btn-sm mb-3">＋ 外部速報リンクを登録</a>

    @forelse($list as $item)
        <div class="border p-2 rounded mb-2 d-flex justify-content-between align-items-center">
            <div>
                <strong>{{ $item->title }}</strong>
                <a href="{{ route('flash_news.public', $item->id) }}" target="_blank" rel="noopener" class="ms-3">公開リンクへ</a>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('flash_news.edit', $item->id) }}" class="btn btn-sm btn-outline-secondary">編集</a>
                <form method="POST" action="{{ route('flash_news.destroy', $item->id) }}" onsubmit="return confirm('この外部速報リンクを削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">削除</button>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-light border mb-0">外部速報リンクはまだ登録されていません。</div>
    @endforelse
</div>
@endsection
