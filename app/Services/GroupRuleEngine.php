<?php

namespace App\Services;

use App\Models\InstructorRegistry;
use App\Models\ProBowler;
use Illuminate\Support\Facades\DB;

class GroupRuleEngine
{
    /**
     * ルール例
     * - {"and":[{"attr":"sex","eq":1},{"attr":"is_district_leader","eq":true},{"exists":"titles"}]}
     * - {"tournament_participant":{"tournament_id":3}}
     * - {"instructor_grade":{"grade":"B"}}
     * - {"annual_dues":{"year":"current","paid":false}}
     */
    public function matches(ProBowler $b, array $rule): bool
    {
        if (!$rule) return false;

        // 論理合成
        if (isset($rule['and'])) {
            foreach ((array) $rule['and'] as $sub) {
                if (!$this->matches($b, (array) $sub)) return false;
            }
            return true;
        }

        if (isset($rule['or'])) {
            foreach ((array) $rule['or'] as $sub) {
                if ($this->matches($b, (array) $sub)) return true;
            }
            return false;
        }

        // 属性比較（アクセサ/キャスト対応）
        if (isset($rule['attr'])) {
            $val = $this->readAttr($b, (string) $rule['attr']);
            return $this->compare($val, $rule);
        }

        // リレーション存在チェック（例: "titles"）
        if (isset($rule['exists'])) {
            $rel = (string) $rule['exists'];
            if (!method_exists($b, $rel)) return false;
            return (bool) $b->$rel()->exists();
        }

        // 大会参加者
        if (isset($rule['tournament_participant'])) {
            $tid = (int) ($rule['tournament_participant']['tournament_id'] ?? 0);
            if ($tid <= 0) return false;

            return $b->entries()
                ->where('tournament_id', $tid)
                ->exists();
        }

        // インストラクター級（A/B/C など）
        if (isset($rule['instructor_grade'])) {
            $grade = $this->normalizeInstructorGrade($rule['instructor_grade']['grade'] ?? null);
            if ($grade === null) return false;

            return InstructorRegistry::query()
                ->where('pro_bowler_id', $b->id)
                ->where('instructor_category', 'pro_bowler')
                ->where('grade', $grade)
                ->exists();
        }

        // 年会費（annual_dues テーブル想定: pro_bowler_id, year, paid_at）
        if (isset($rule['annual_dues'])) {
            $yearParam = $rule['annual_dues']['year'] ?? 'current';
            $year = ($yearParam === 'current') ? now()->year : (int) $yearParam;
            $wantPaid = (bool) ($rule['annual_dues']['paid'] ?? false);

            $paid = DB::table('annual_dues')
                ->where('pro_bowler_id', $b->id)
                ->where('year', $year)
                ->whereNotNull('paid_at')
                ->exists();

            return $wantPaid ? $paid : !$paid;
        }

        return false;
    }

    /** Eloquentのアクセサ/キャストを通して安全に取得 */
    private function readAttr(ProBowler $b, string $attr)
    {
        // getAttribute 経由でアクセサ & casts を尊重
        if ($b->offsetExists($attr)) return $b->getAttribute($attr);

        // 念のため data_get のフォールバック
        return data_get($b, $attr);
    }

    /** ルール入力の級表記をDB保存値へ寄せる */
    private function normalizeInstructorGrade($grade): ?string
    {
        if ($grade === null) {
            return null;
        }

        $g = trim((string) $grade);
        if ($g === '') {
            return null;
        }

        $g = mb_convert_kana($g, 'asKV', 'UTF-8');
        $g = str_replace([' ', '　'], '', $g);
        $g = mb_strtoupper($g, 'UTF-8');

        $map = [
            'A' => 'A級',
            'A級' => 'A級',
            'B' => 'B級',
            'B級' => 'B級',
            'C' => 'C級',
            'C級' => 'C級',
            '1' => '1級',
            '1級' => '1級',
            '2' => '2級',
            '2級' => '2級',
            '準A' => '準A級',
            '準A級' => '準A級',
            '準B' => '準B級',
            '準B級' => '準B級',
            '準C' => '準C級',
            '準C級' => '準C級',
        ];

        return $map[$g] ?? $g;
    }

    /** 単純比較演算子 */
    private function compare($val, array $rule): bool
    {
        if (array_key_exists('eq', $rule)) return $val == $rule['eq'];
        if (array_key_exists('neq', $rule)) return $val != $rule['neq'];
        if (array_key_exists('in', $rule)) return in_array($val, (array) $rule['in'], true);
        if (array_key_exists('nin', $rule)) return !in_array($val, (array) $rule['nin'], true);

        return false;
    }
}