<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('tournament_entry_balls', function (Blueprint $table) {
      if (!Schema::hasColumn('tournament_entry_balls','tournament_entry_id')) {
        $table->foreignId('tournament_entry_id')->constrained('tournament_entries')->cascadeOnDelete();
      }
      if (!Schema::hasColumn('tournament_entry_balls','used_ball_id')) {
        $table->foreignId('used_ball_id')->constrained('used_balls')->cascadeOnDelete();
      }
      $table->unique(['tournament_entry_id','used_ball_id'],'teb_unique');
    });
  }
  public function down(): void {
    Schema::table('tournament_entry_balls', function (Blueprint $table) {
      $table->dropUnique('teb_unique');
    });
  }
};
