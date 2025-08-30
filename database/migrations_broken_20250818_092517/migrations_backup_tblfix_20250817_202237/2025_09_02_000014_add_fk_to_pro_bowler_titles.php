<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('pro_bowler_titles', function (Blueprint $t) {
            if (!Schema::hasColumn('pro_bowler_titles','tournament_id')) {
                $t->foreignId('tournament_id')->nullable()
                  ->constrained('tournaments')->nullOnDelete(); // or restrict/cascade
            }
            if (!Schema::hasColumn('pro_bowler_titles','tournament_name')) {
                // 表示用スナップショット（将来名前が変わっても見た目は固定したいとき用）
                $t->string('tournament_name')->nullable();
            }
            if (!Schema::hasColumn('pro_bowler_titles','year')) {
                $t->integer('year')->nullable()->index();
            }
            if (!Schema::hasColumn('pro_bowler_titles','won_date')) {
                $t->date('won_date')->nullable();
            }

            // よく使う組み合わせにインデックス
            $t->index(['pro_bowler_id','tournament_id']);
        });
    }
    public function down() {
        Schema::table('pro_bowler_titles', function (Blueprint $t) {
            if (Schema::hasColumn('pro_bowler_titles','won_date')) $t->dropColumn('won_date');
            if (Schema::hasColumn('pro_bowler_titles','year')) $t->dropColumn('year');
            if (Schema::hasColumn('pro_bowler_titles','tournament_name')) $t->dropColumn('tournament_name');
            if (Schema::hasColumn('pro_bowler_titles','tournament_id')) {
                $t->dropConstrainedForeignId('tournament_id');
            }
        });
    }
};
