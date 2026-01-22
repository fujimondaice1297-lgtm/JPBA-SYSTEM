<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Instructor;
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
            foreach ((array)$rule['and'] as $sub) {
                if (!$this->matches($b, (array)$sub)) return false;
            }
            return true;
        }
        if (isset($rule['or'])) {
            foreach ((array)$rule['or'] as $sub) {
                if ($this->matches($b, (array)$sub)) return true;
            }
            return false;
        }

        // 属性比較（アクセサ/キャスト対応）
        if (isset($rule['attr'])) {
            $val = $this->readAttr($b, (string)$rule['attr']);
            return $this->compare($val, $rule);
        }

        // リレーション存在チェック（例: "titles"）
        if (isset($rule['exists'])) {
            $rel = (string)$rule['exists'];
            if (!method_exists($b, $rel)) return false;
            return (bool) $b->$rel()->exists();
        }

        // 大会参加者
        if (isset($rule['tournament_participant'])) {
            $tid = (int)($rule['tournament_participant']['tournament_id'] ?? 0);
            if ($tid <= 0) return false;
            return $b->entries()->where('tournament_id', $tid)->exists();
        }

        // インストラクター級（A/B/C）
        if (isset($rule['instructor_grade'])) {
            $g = strtoupper((string)($rule['instructor_grade']['grade'] ?? ''));
            if ($g === '') return false;
            return Instructor::proBowler()
                ->where('pro_bowler_id', $b->id)
                ->where('grade', $g)
                ->exists();
        }

        // 年会費（annual_dues テーブル想定: pro_bowler_id, year, paid_at）
        if (isset($rule['annual_dues'])) {
            $yearParam = $rule['annual_dues']['year'] ?? 'current';
            $year = ($yearParam === 'current') ? now()->year : (int)$yearParam;
            $wantPaid = (bool)($rule['annual_dues']['paid'] ?? false);

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

    /** 単純比較演算子 */
    private function compare($val, array $rule): bool
    {
        if (array_key_exists('eq', $rule))   return $val == $rule['eq'];
        if (array_key_exists('neq', $rule))  return $val != $rule['neq'];
        if (array_key_exists('in', $rule))   return in_array($val, (array)$rule['in'], true);
        if (array_key_exists('nin', $rule))  return !in_array($val, (array)$rule['nin'], true);
        return false;
    }
}
