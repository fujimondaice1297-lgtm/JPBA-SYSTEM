<div class="official-result-page {{ !($isSeasonTrialPdf ?? false) ? 'official-standard-result-page' : '' }}">
    @include('tournament_results.pdfs.partials.header')
    @include('tournament_results.pdfs.partials.award_list')

    <div class="official-record-box jpba-heavy">
        <div class="official-record-title">☆パーフェクトゲーム達成者</div>
        <div>該当データがある場合はここに表示します。</div>
    </div>

    <div class="official-borderless-note jpba-heavy">
        ※このPDFは登録済み大会成績データをもとに出力しています。詳細成績表は次ページ以降に表示します。
    </div>
</div>
