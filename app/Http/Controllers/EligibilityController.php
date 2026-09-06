<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class EligibilityController extends Controller
{
    /* ============================== 共通ユーティリティ ============================== */

    /** 名前での並び（かな→漢字→ID） */
    private function orderByFor(Builder $q): Builder
    {
        $table = (new ProBowler)->getTable();

        if (Schema::hasColumn($table, 'name_kana')) {
            $q->orderBy('name_kana');
        }
        if (Schema::hasColumn($table, 'name_kanji')) {
            $q->orderBy('name_kanji');
        }

        return $q->orderBy('id');
    }

    /** 一覧に必要な最小項目をマップ */
    private function pickForList(ProBowler $p): array
    {
        $name = $p->name_kanji ?? $p->name ?? '';

        return [
            'id' => $p->id,
            'name' => $name,
            'name_kana' => $p->name_kana ?? null,
            'license_no' => $p->license_no ?? null,   // 表示時に英字を落とす
            'sex' => (int) ($p->sex ?? 0),      // 1:男, 2:女
            'portrait_url' => $p->public_photo_url,
            'a_number' => $p->a_license_number ?? null,
            'seed_date' => $p->permanent_seed_date ? (string) $p->permanent_seed_date : null,
        ];
    }

    /* ============================== ページ毎アクション ============================== */

    /** 永久シード保持者（条件表示あり） */
    public function evergreen()
    {
        $q = ProBowler::query()
            ->whereNotNull('permanent_seed_date');

        $this->orderByFor($q); // 名前順

        $rows = $q->get()->map(fn ($p) => $this->pickForList($p))->all();

        $page = [
            'code' => 'evergreen',
            'title' => '永久シードプロ',
            'conditions' => [
                'JPBA主催・公認トーナメント全てに出場する権利を有する者（または出場資格を有する者）をいう。',
                '永久シード権獲得条件：JPBA公認トーナメント（個人戦）において、生涯優勝回数20勝以上。',
            ],
        ];

        return view('eligibility.list', compact('page', 'rows'));
    }

    /** 男子A級ライセンス保持者 */
    public function aClassMen()
    {
        return $this->aClass('M');
    }

    /** 女子A級ライセンス保持者 */
    public function aClassWomen()
    {
        return $this->aClass('F');
    }

    /** 内部実装（A級共通） */
    private function aClass(string $sex)
    {
        $sexVal = ($sex === 'M') ? 1 : 2;

        $q = ProBowler::query()
            ->whereNotNull('a_license_number')
            ->where('a_license_number', '>', 0)
            ->where('sex', $sexVal);

        // ① A級番号の昇順を最優先、その後は名前順の安定ソート
        $q->orderBy('a_license_number');
        $this->orderByFor($q);

        $rows = $q->get()->map(fn ($p) => $this->pickForList($p))->all();

        $page = [
            'code' => $sex === 'M' ? 'a_m' : 'a_f',
            'title' => $sex === 'M' ? '男子 永久A級ライセンス保持者' : '女子 永久A級ライセンス保持者',
            'conditions' => $sex === 'M'
                ? [
                    '2007年度まで：5年連続200ゲーム・200アベレージ以上達成した者',
                    '2008年度より：5年連続200ゲーム・210アベレージ以上達成した者',
                    '2011年度より：200ゲーム・210アベレージ以上、またはシード権（48位以内）のいずれかを5年連続達成・獲得した者',
                    '2022年度より：200ゲーム・210アベレージ以上、またはシード権（24位以内）のいずれかを5年連続達成・獲得した者、および全日本選手権者',
                    '2025年度より：A公認・B公認の対象総ゲーム数の85％以上かつ210アベレージ以上、またはシード権（24位以内）のいずれかを5年連続達成・獲得した者、および全日本選手権者',
                ]
                : [
                    '2007年度まで：5年連続200ゲーム・190アベレージ以上達成した者',
                    '2008年度より：5年連続200ゲーム・200アベレージ以上達成した者',
                    '2011年度より：200ゲーム・200アベレージ以上、またはシード権（18位以内）のいずれかを5年連続達成・獲得した者、および全日本女子選手権者',
                    '2025年度より：A公認・B公認の対象総ゲーム数の85％以上かつ200アベレージ以上、またはシード権（18位以内）のいずれかを5年連続達成・獲得した者、および全日本女子選手権者',
                ],
        ];

        return view('eligibility.list', compact('page', 'rows'));
    }

    /* 互換（旧ルート対応） */
    public function maleA()
    {
        return $this->aClass('M');
    }

    public function femaleA()
    {
        return $this->aClass('F');
    }
}
