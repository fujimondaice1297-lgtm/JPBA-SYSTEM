<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_archives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->string('title');
            $table->date('start_on')->nullable()->index();
            $table->date('end_on')->nullable();
            $table->string('status', 24)->default('completed')->index();
            $table->longText('body_html');
            $table->jsonb('assets')->default('[]');
            $table->string('source_key', 190)->unique();
            $table->text('source_url')->nullable();
            $table->char('source_fingerprint', 64)->nullable();
            $table->timestamp('source_synced_at')->nullable();
            $table->boolean('is_public')->default(true)->index();
            $table->timestamps();

            $table->index(['year', 'start_on', 'id'], 'tournament_archives_year_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_archives');
    }
};
