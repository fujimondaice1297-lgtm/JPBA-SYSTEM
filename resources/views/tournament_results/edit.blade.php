@extends('layouts.app')

@section('content')
<div class="container py-3" style="max-width: 900px;">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1">大会成績 編集</h2>
            <div class="text-muted small">
                順位を変更すると、設定済みのポイント配分・賞金配分に応じて再計算されます。
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('tournaments.results.index', $result->tournament_id) }}" class="btn btn-secondary">この大会の成績一覧へ戻る</a>
            <a href="{{ route('tournament_results.index') }}" class="btn btn-outline-dark">大会検索へ戻る</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>入力内容に誤りがあります。</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info py-2">
        この画面で保存すると、その順位に対応する <strong>ポイント</strong> と <strong>賞金</strong> が自動再計算されます。<br>
        配分表を後から変更した場合だけ、成績一覧で <strong>賞金・ポイント再計算</strong> を実行してください。
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('tournament_results.update', $result->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="pro_bowler_license_no" class="form-label">選手</label>
                        <select name="pro_bowler_license_no" id="pro_bowler_license_no" class="form-select">
                            @foreach ($players as $player)
                                <option value="{{ $player->license_no }}" {{ $player->license_no == $result->pro_bowler_license_no ? 'selected' : '' }}>
                                    {{ $player->name_kanji }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="tournament_id" class="form-label">大会</label>
                        <select name="tournament_id" id="tournament_id" class="form-select">
                            @foreach ($tournaments as $tournament)
                                <option value="{{ $tournament->id }}" {{ $tournament->id == $result->tournament_id ? 'selected' : '' }}>
                                    {{ $tournament->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3 bg-light">
                            <div class="small text-muted mb-2">選択中大会の補助導線</div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="#" id="helper-results" class="btn btn-outline-secondary btn-sm">成績一覧</a>
                                <a href="#" id="helper-point" class="btn btn-outline-danger btn-sm">ポイント配分</a>
                                <a href="#" id="helper-prize" class="btn btn-outline-warning btn-sm">賞金配分</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="ranking" class="form-label">順位</label>
                        <input type="number" name="ranking" id="ranking" class="form-control" value="{{ $result->ranking }}" min="1">
                    </div>

                    <div class="col-md-3">
                        <label for="total_pin" class="form-label">トータルピン</label>
                        <input type="number" name="total_pin" id="total_pin" class="form-control" value="{{ $result->total_pin }}" min="0">
                    </div>

                    <div class="col-md-3">
                        <label for="games" class="form-label">ゲーム数</label>
                        <input type="number" name="games" id="games" class="form-control" value="{{ $result->games }}" min="1">
                    </div>

                    <div class="col-md-3">
                        <label for="ranking_year" class="form-label">ランキング年度</label>
                        <input type="number" name="ranking_year" id="ranking_year" class="form-control" value="{{ $result->ranking_year }}">
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3 bg-light">
                            <div class="small text-muted mb-1">平均目安（保存時に自動計算）</div>
                            <div id="average-preview" class="fw-bold">-</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">更新する</button>
                    <a href="{{ route('tournaments.results.index', $result->tournament_id) }}" class="btn btn-outline-secondary">この大会の成績一覧へ戻る</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tournamentSelect = document.getElementById('tournament_id');
    const helperResults    = document.getElementById('helper-results');
    const helperPoint      = document.getElementById('helper-point');
    const helperPrize      = document.getElementById('helper-prize');
    const totalPinInput    = document.getElementById('total_pin');
    const gamesInput       = document.getElementById('games');
    const avgPreview       = document.getElementById('average-preview');

    const resultsTpl = @json(url('/tournaments/__ID__/results'));
    const pointTpl   = @json(route('tournaments.point_distributions.create', ['tournament' => '__ID__']));
    const prizeTpl   = @json(route('tournaments.prize_distributions.create', ['tournament' => '__ID__']));

    function syncHelperLinks() {
        const id = tournamentSelect.value;
        helperResults.href = resultsTpl.replace('__ID__', id);
        helperPoint.href   = pointTpl.replace('__ID__', id);
        helperPrize.href   = prizeTpl.replace('__ID__', id);
    }

    function updateAverage() {
        const totalPin = parseFloat(totalPinInput.value || 0);
        const games    = parseFloat(gamesInput.value || 0);

        if (totalPin > 0 && games > 0) {
            avgPreview.textContent = (totalPin / games).toFixed(2);
        } else {
            avgPreview.textContent = '-';
        }
    }

    tournamentSelect.addEventListener('change', syncHelperLinks);
    [totalPinInput, gamesInput].forEach(el => el.addEventListener('input', updateAverage));

    syncHelperLinks();
    updateAverage();
});
</script>
@endpush