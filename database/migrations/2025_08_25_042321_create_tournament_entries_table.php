<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('tournament_entries', function (Blueprint $table) {
        $table->id();

        // 選手（pro_bowler_id）と大会（tournament_id）に紐づく
        $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->onDelete('cascade');
        $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');

        // entry, no_entry, paid, shift_drawn, lane_drawnなど
        $table->string('status')->default('entry');

        // 管理系フラグ（今後の拡張を見越して）
        $table->boolean('is_paid')->default(false);
        $table->boolean('shift_drawn')->default(false);
        $table->boolean('lane_drawn')->default(false);

        $table->timestamps();
    });
}

};
