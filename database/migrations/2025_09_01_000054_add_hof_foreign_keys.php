<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * “後から” 外部キーを追加する安全版。
 * - テーブルが存在する場合にのみ実行（存在しなければスキップ）。
 * - PostgreSQLでも落ちないよう IF EXISTS を併用。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hof_inductions') && Schema::hasTable('pros')) {
            // 既に付いていない場合のみ付与（同名制約があると落ちるためIF NOT EXISTS相当の回避）
            try {
                Schema::table('hof_inductions', function (Blueprint $table) {
                    $table->foreign('pro_id', 'fk_hof_pro')
                          ->references('id')->on('pros')
                          ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // 既に存在などは無視（再実行にも耐える）
            }
        }

        if (Schema::hasTable('hof_photos') && Schema::hasTable('hof_inductions')) {
            try {
                Schema::table('hof_photos', function (Blueprint $table) {
                    $table->foreign('hof_id', 'fk_hof_photos_hof')
                          ->references('id')->on('hof_inductions')
                          ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // 同上
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hof_photos')) {
            // Postgres: 制約名でDROP（存在しなくてもOKにする）
            DB::statement('ALTER TABLE hof_photos DROP CONSTRAINT IF EXISTS fk_hof_photos_hof');
        }
        if (Schema::hasTable('hof_inductions')) {
            DB::statement('ALTER TABLE hof_inductions DROP CONSTRAINT IF EXISTS fk_hof_pro');
        }
    }
};
