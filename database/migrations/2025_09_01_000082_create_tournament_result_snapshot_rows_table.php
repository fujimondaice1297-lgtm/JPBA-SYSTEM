<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_result_snapshot_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('tournament_result_snapshots')->cascadeOnDelete();
            $table->unsignedInteger('ranking');
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('pro_bowler_license_no')->nullable();
            $table->string('amateur_name')->nullable();
            $table->string('display_name');
            $table->string('gender', 1)->nullable();
            $table->string('shift', 16)->nullable();
            $table->string('entry_number', 32)->nullable();
            $table->unsignedInteger('scratch_pin')->default(0);
            $table->unsignedInteger('carry_pin')->default(0);
            $table->unsignedInteger('total_pin')->default(0);
            $table->unsignedInteger('games')->default(0);
            $table->decimal('average', 6, 3)->nullable();
            $table->unsignedInteger('tie_break_value')->nullable();
            $table->integer('points')->nullable();
            $table->decimal('prize_money', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['snapshot_id', 'ranking'], 'trsr_snapshot_ranking_idx');
            $table->index(['snapshot_id', 'pro_bowler_id'], 'trsr_snapshot_bowler_idx');
            $table->index(['snapshot_id', 'pro_bowler_license_no'], 'trsr_snapshot_license_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_result_snapshot_rows');
    }
};
