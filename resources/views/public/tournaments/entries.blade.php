@extends('public.layout')

@section('title', 'エントリープロ｜' . $tournament->name . '｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'エントリープロ')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
    <div>
        <h1 class="jpba-page-title mb-1">エントリープロ</h1>
        <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <a class="jpba-small-button" href="{{ route('public.tournaments.show', $tournament) }}">大会ページへ戻る</a>
</div>

<section class="jpba-panel">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-end mb-3">
        <div>
            <div class="text-muted small">エントリー人数</div>
            <div class="fs-3 fw-bold">{{ number_format($entryCount) }}名</div>
        </div>
        <form method="GET" action="{{ route('public.tournaments.entries', $tournament) }}" class="d-flex gap-2 flex-wrap">
            <input
                type="search"
                name="q"
                value="{{ $keyword }}"
                class="form-control"
                placeholder="氏名・ライセンスNo."
                aria-label="選手検索"
            >
            <button type="submit" class="jpba-small-button">検索</button>
            @if($keyword !== '')
                <a href="{{ route('public.tournaments.entries', $tournament) }}" class="jpba-small-button">解除</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="jpba-data-table">
            <thead>
                <tr>
                    <th>選手</th>
                    <th>ライセンスNo.</th>
                    <th>大会登録ボール</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    @php($bowler = $entry->bowler)
                    <tr>
                        <td>
                            <a
                                href="{{ route('scores.entry_balls.show', [
                                    'entry' => $entry,
                                    'public' => 1,
                                    'return' => route('public.tournaments.entries', $tournament),
                                ]) }}"
                                class="d-inline-flex align-items-center gap-2 fw-bold"
                            >
                                @if($bowler?->public_photo_url)
                                    <img
                                        src="{{ $bowler->public_photo_url }}"
                                        alt=""
                                        style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid #dbe3ec;"
                                    >
                                @endif
                                <span>{{ $bowler?->name_kanji ?? '選手名未設定' }}</span>
                            </a>
                        </td>
                        <td>{{ \App\Support\PublicLicenseNumber::format($bowler?->license_no) }}</td>
                        <td>
                            <a href="{{ route('scores.entry_balls.show', [
                                'entry' => $entry,
                                'public' => 1,
                                'return' => route('public.tournaments.entries', $tournament),
                            ]) }}">
                                {{ number_format((int) $entry->balls_count) }}個を見る
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">公開できるエントリープロはまだ登録されていません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
</section>
@endsection
