<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0) sexes が存在する前提（存在しないならここで落ちる）
        if (!Schema::hasTable('sexes')) {
            throw new RuntimeException('sexes table not found.');
        }

        // 1) sexes に「1=男性 / 2=女性」を投入（既にあっても上書き更新する）
        // 可能なら update_date / created_by / updated_by も埋める（存在する列だけ使う）
        $cols = Schema::getColumnListing('sexes');

        $base = [
            'update_date' => DB::raw('CURRENT_TIMESTAMP'),
            'created_by'  => 0,
            'updated_by'  => 0,
        ];
        $sex1 = array_intersect_key(array_merge(['id' => 1, 'label' => '男性'], $base), array_flip($cols));
        $sex2 = array_intersect_key(array_merge(['id' => 2, 'label' => '女性'], $base), array_flip($cols));

        // IDを明示して upsert（PostgreSQL）
        $this->upsertById('sexes', $sex1);
        $this->upsertById('sexes', $sex2);

        // 2) FKを貼るため、pro_bowlers.sex の型を sexes.id に合わせる
        $sexesIdType = DB::selectOne("
            SELECT udt_name
            FROM information_schema.columns
            WHERE table_schema='public' AND table_name='sexes' AND column_name='id'
        ");

        $proSexType = DB::selectOne("
            SELECT udt_name
            FROM information_schema.columns
            WHERE table_schema='public' AND table_name='pro_bowlers' AND column_name='sex'
        ");

        if ($sexesIdType && $proSexType && $sexesIdType->udt_name !== $proSexType->udt_name) {
            if ($sexesIdType->udt_name === 'int8') {
                DB::statement("ALTER TABLE public.pro_bowlers ALTER COLUMN sex TYPE bigint USING sex::bigint");
            } elseif ($sexesIdType->udt_name === 'int4') {
                DB::statement("ALTER TABLE public.pro_bowlers ALTER COLUMN sex TYPE integer USING sex::integer");
            }
        }

        // 3) pro_bowlers.sex の index + FK（重複追加しない）
        DB::statement("CREATE INDEX IF NOT EXISTS idx_pro_bowlers_sex ON public.pro_bowlers USING btree (sex)");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pro_bowlers_sex_foreign') THEN
                    ALTER TABLE public.pro_bowlers
                    ADD CONSTRAINT pro_bowlers_sex_foreign
                    FOREIGN KEY (sex) REFERENCES public.sexes(id)
                    ON UPDATE RESTRICT
                    ON DELETE RESTRICT;
                END IF;
            END$$;
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_pro_bowlers_sex");

        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pro_bowlers_sex_foreign') THEN
                    ALTER TABLE public.pro_bowlers DROP CONSTRAINT pro_bowlers_sex_foreign;
                END IF;
            END$$;
        ");

        // sexesの2行は消さない（他テーブルが参照し始めると危険なため）
    }

    private function upsertById(string $table, array $row): void
    {
        // id が無いなら何もしない
        if (!array_key_exists('id', $row)) {
            return;
        }

        $columns = array_keys($row);
        $placeholders = [];
        $bindings = [];

        foreach ($columns as $col) {
            $val = $row[$col];
            if ($val instanceof \Illuminate\Database\Query\Expression) {
                $placeholders[] = $val->getValue(DB::connection()->getQueryGrammar());
            } else {
                $placeholders[] = '?';
                $bindings[] = $val;
            }
        }

        $set = [];
        foreach ($columns as $col) {
            if ($col === 'id') continue;
            $set[] = "{$col} = EXCLUDED.{$col}";
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (id) DO UPDATE SET %s",
            $table,
            implode(',', $columns),
            implode(',', $placeholders),
            implode(',', $set)
        );

        DB::statement($sql, $bindings);
    }
};
