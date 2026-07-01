<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_entry_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_entry_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->string('batch_key', 64)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['tournament_id', 'occurred_at'], 'teol_tournament_occurred_idx');
            $table->index(['tournament_entry_id', 'occurred_at'], 'teol_entry_occurred_idx');
            $table->index(['action', 'occurred_at'], 'teol_action_occurred_idx');
            $table->index('batch_key', 'teol_batch_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_entry_operation_logs');
    }
};
