<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('informations', function (Blueprint $table) {
            // JPBAサイトの一覧に「カテゴリ」が出るため（例：NEWS / 大会 / イベント / ｲﾝｽﾄﾗｸﾀｰ）
            if (!Schema::hasColumn('informations', 'category')) {
                $table->string('category', 32)->default('NEWS');
                $table->index('category');
            }

            // JPBAサイトの表示日（一覧の先頭に出る日付）
            if (!Schema::hasColumn('informations', 'published_at')) {
                $table->timestamp('published_at')->nullable();
                $table->index('published_at');
            }

            // required_training_id -> trainings.id をFKで確定
            // 既にカラムはある前提（create_information_table.php に定義済み）
            $table->foreign('required_training_id')
                ->references('id')->on('trainings')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('informations', function (Blueprint $table) {
            // FK
            $table->dropForeign(['required_training_id']);

            // columns/index
            if (Schema::hasColumn('informations', 'published_at')) {
                $table->dropIndex(['published_at']);
                $table->dropColumn('published_at');
            }
            if (Schema::hasColumn('informations', 'category')) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            }
        });
    }
};
