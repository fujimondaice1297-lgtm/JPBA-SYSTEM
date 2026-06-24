<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_import_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_import_batch_id')->nullable()->constrained('score_import_batches')->cascadeOnDelete();
            $table->string('action', 50);
            $table->string('status', 50)->default('success');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('target_row_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['tournament_id', 'occurred_at'], 'siol_tournament_occurred_idx');
            $table->index(['score_import_batch_id', 'occurred_at'], 'siol_batch_occurred_idx');
            $table->index(['action', 'status'], 'siol_action_status_idx');
            $table->index('actor_user_id', 'siol_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_import_operation_logs');
    }
};
