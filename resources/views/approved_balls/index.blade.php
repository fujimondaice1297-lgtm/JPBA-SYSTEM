@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1">ボールカタログ</h2>
            <p class="text-muted mb-0">メーカー公式サイト掲載品を、メーカー・ブランド・五十音順で表示します。</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('approved_balls.create') }}" class="btn btn-success">+ 手動登録</a>
            <a href="{{ route('approved_balls.import_form') }}" class="btn btn-outline-secondary">CSVインポート</a>
            <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
        </div>
    </div>

    @if($catalogSummary->isNotEmpty())
        <div class="row g-2 mb-3">
            @foreach($catalogSummary as $summary)
                <div class="col-12 col-md-4">
                    <a href="{{ route('approved_balls.index', ['manufacturer' => $summary->name]) }}"
                       class="card h-100 text-decoration-none text-body">
                        <div class="card-body py-3">
                            <div class="fw-bold">{{ $summary->name }}</div>
                            <div class="small text-muted">
                                {{ number_format($summary->approved_balls_count) }}件 /
                                写真{{ number_format($summary->image_count) }}件
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('approved_balls.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label for="manufacturer" class="form-label">メーカー</label>
                <select id="manufacturer" name="manufacturer" class="form-select">
                    <option value="">すべて</option>
                    @foreach($manufacturers as $manufacturer)
                        <option value="{{ $manufacturer }}" @selected(request('manufacturer') === $manufacturer)>
                            {{ $manufacturer }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label for="brand" class="form-label">ブランド</label>
                <select id="brand" name="brand" class="form-select">
                    <option value="">すべて</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand }}" @selected(request('brand') === $brand)>
                            {{ $brand }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label for="catalog_status" class="form-label">掲載状態</label>
                <select id="catalog_status" name="catalog_status" class="form-select">
                    <option value="">すべて</option>
                    <option value="listed" @selected(request('catalog_status') === 'listed')>掲載中</option>
                    <option value="archive" @selected(request('catalog_status') === 'archive')>アーカイブ</option>
                    <option value="manual" @selected(request('catalog_status') === 'manual')>手動登録</option>
                    <option value="hidden" @selected(request('catalog_status') === 'hidden')>非表示</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="name" class="form-label">ボール名・カナ・ブランド</label>
                <input id="name" type="search" name="name" class="form-control"
                       value="{{ request('name') }}" placeholder="例：アキュライン">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">検索</button>
                <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary">解除</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width:88px">写真</th>
                    <th>メーカー / ブランド</th>
                    <th>ボール名</th>
                    <th>発売時期</th>
                    <th>掲載状態</th>
                    <th>大会選択</th>
                    <th style="width:150px">操作</th>
                </tr>
            </thead>
            <tbody>
            @forelse($balls as $ball)
                <tr>
                    <td class="text-center">
                        <img src="{{ $ball->image_url }}" alt="{{ $ball->name }}"
                             width="68" height="68"
                             style="object-fit:contain;background:#f8f9fa;border-radius:.4rem"
                             loading="lazy">
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $ball->manufacturer }}</div>
                        <div class="small text-muted">{{ $ball->brand ?: 'ブランド未設定' }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $ball->name }}</div>
                        @if($ball->name_kana)
                            <div class="small text-muted">{{ $ball->name_kana }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $ball->release_display }}
                    </td>
                    <td>
                        @switch($ball->catalog_status)
                            @case('archive')
                                <span class="badge text-bg-secondary">アーカイブ</span>
                                @break
                            @case('hidden')
                                <span class="badge text-bg-dark">非表示</span>
                                @break
                            @case('manual')
                                <span class="badge text-bg-info">手動登録</span>
                                @break
                            @default
                                <span class="badge text-bg-success">掲載中</span>
                        @endswitch
                    </td>
                    <td>
                        @if($ball->approved)
                            <span class="badge text-bg-primary">選択可</span>
                        @else
                            <span class="text-muted small">未設定</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <a href="{{ route('approved_balls.edit', $ball) }}"
                               class="btn btn-sm btn-outline-primary">編集</a>
                            @if($ball->source_url)
                                <a href="{{ $ball->source_url }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-secondary">公式</a>
                            @endif
                            @if(auth()->user()?->isAdmin())
                                <form action="{{ route('admin.approved_balls.destroy', $ball) }}"
                                      method="POST"
                                      onsubmit="return confirm('このカタログ行を削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">削除</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">該当するボールはありません。</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center">
        {{ $balls->links() }}
    </div>
</div>
@endsection
