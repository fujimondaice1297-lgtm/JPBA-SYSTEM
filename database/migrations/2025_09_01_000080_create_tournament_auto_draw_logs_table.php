<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tournament_auto_draw_logs')) {
            return;
        }

        Schema::create('tournament_auto_draw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('target_type', 10); // shift / lane
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('executed_at');
            $table->unsignedInteger('total_pending')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('details_json')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'target_type', 'executed_at'], 'tadl_tournament_target_executed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_auto_draw_logs');
    }
};