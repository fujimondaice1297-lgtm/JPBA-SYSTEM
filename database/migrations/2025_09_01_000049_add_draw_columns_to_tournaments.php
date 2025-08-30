<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tournaments', function (Blueprint $table) {
            // シフト抽選の受付期間（任意）
            $table->timestamp('shift_draw_open_at')->nullable()->after('entry_end');
            $table->timestamp('shift_draw_close_at')->nullable()->after('shift_draw_open_at');
            // レーン抽選の受付期間（任意）
            $table->timestamp('lane_draw_open_at')->nullable()->after('shift_draw_close_at');
            $table->timestamp('lane_draw_close_at')->nullable()->after('lane_draw_open_at');

            // 大会で使うレーンの範囲（例：1〜40）
            $table->unsignedSmallInteger('lane_from')->nullable()->after('lane_draw_close_at');
            $table->unsignedSmallInteger('lane_to')->nullable()->after('lane_from');

            // シフトの候補（A/B/C …）をCSVで持つだけの簡易版。必要なら別テーブルに分離してもOK。
            $table->string('shift_codes', 50)->nullable()->after('lane_to'); // 例 "A,B,C"
            // シフトごとの上限を全体均等に割る簡便運用の場合は不要。細かい上限が必要なら別テーブルを用意。
        });
    }

    public function down(): void {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'shift_draw_open_at','shift_draw_close_at',
                'lane_draw_open_at','lane_draw_close_at',
                'lane_from','lane_to','shift_codes'
            ]);
        });
    }
};
