<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $table = 'game_scores';
    private string $column = 'pro_bowler_id';
    private string $fkName = 'game_scores_pro_bowler_id_foreign';
    private string $indexName = 'game_scores_pro_bowler_id_index';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        // 1) column（重複エラー対策）
        if (!Schema::hasColumn($this->table, $this->column)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unsignedBigInteger($this->column)->nullable();
            });
        }

        // 2) backfill（license_no -> id）
        if (Schema::hasColumn($this->table, 'pro_bowler_license_no')) {
            DB::statement("
                UPDATE {$this->table} gs
                   SET {$this->column} = pb.id
                  FROM pro_bowlers pb
                 WHERE gs.{$this->column} IS NULL
                   AND gs.pro_bowler_license_no IS NOT NULL
                   AND pb.license_no = gs.pro_bowler_license_no
            ");
        }

        // 3) index（PostgreSQLのみ IF NOT EXISTS 可）
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS {$this->indexName} ON {$this->table} ({$this->column})");
        }

        // 4) FK（存在チェックしてから追加）
        if (!$this->foreignKeyExists()) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->foreign($this->column, $this->fkName)
                    ->references('id')->on('pro_bowlers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        if ($this->foreignKeyExists()) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropForeign($this->fkName);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$this->indexName}");
        }

        if (Schema::hasColumn($this->table, $this->column)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropColumn($this->column);
            });
        }
    }

    private function foreignKeyExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $row = DB::selectOne(
            "select 1
               from pg_constraint c
               join pg_class t on t.oid = c.conrelid
              where t.relname = ?
                and c.conname = ?
              limit 1",
            [$this->table, $this->fkName]
        );

        return (bool) $row;
    }
};
