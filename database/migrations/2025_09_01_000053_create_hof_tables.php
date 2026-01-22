<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 殿堂テーブル作成のみ（外部キーは張らない）。
 * FKは後続の add_hof_foreign_keys.php で追加する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hof_inductions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pro_id');      // pros.id と紐付け予定（FKは後で）
            $table->smallInteger('year');              // 殿堂入り年（西暦）
            $table->text('citation')->nullable();
            $table->timestampsTz();

            $table->unique('pro_id');                  // 同一プロの重複殿堂入り防止
            $table->index(['year']);
        });

        // Postgresの年チェック制約
        DB::statement("ALTER TABLE hof_inductions
                       ADD CONSTRAINT hof_inductions_year_check
                       CHECK (year BETWEEN 1900 AND 2100)");

        Schema::create('hof_photos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hof_id');      // hof_inductions.id と紐付け予定（FKは後で）
            $table->text('url');
            $table->string('credit')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['hof_id','sort_order']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('hof_photos');
        Schema::dropIfExists('hof_inductions');
        Schema::enableForeignKeyConstraints();
    }
};
