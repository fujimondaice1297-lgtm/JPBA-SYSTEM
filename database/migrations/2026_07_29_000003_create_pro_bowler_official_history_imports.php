<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_official_history_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->unsignedInteger('annual_row_count')->default(0);
            $table->unsignedInteger('participation_year_count')->default(0);
            $table->unsignedInteger('tournament_row_count')->default(0);
            $table->text('source_url')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(
                'pro_bowler_id',
                'bowler_official_history_imports_bowler_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_official_history_imports');
    }
};
