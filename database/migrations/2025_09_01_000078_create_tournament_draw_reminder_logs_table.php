<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tournament_draw_reminder_logs')) {
            return;
        }

        Schema::create('tournament_draw_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('tournament_entry_id')->constrained('tournament_entries')->cascadeOnDelete();
            $table->string('reminder_kind', 20); // manual / auto
            $table->string('pending_type', 10);  // shift / lane / either
            $table->date('scheduled_for_date')->nullable();
            $table->string('dispatch_key')->nullable()->unique();
            $table->string('recipient_email');
            $table->string('subject', 200);
            $table->string('status', 20)->default('sent'); // sent / failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'pending_type'], 'tdrl_tournament_pending_idx');
            $table->index(['scheduled_for_date'], 'tdrl_scheduled_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_draw_reminder_logs');
    }
};