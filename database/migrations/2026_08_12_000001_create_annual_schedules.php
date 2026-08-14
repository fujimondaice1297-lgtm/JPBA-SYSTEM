<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('title')->default('トーナメント年間予定表');
            $table->date('source_updated_on')->nullable();
            $table->text('source_url')->nullable();
            $table->text('notice')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'year'], 'annual_schedules_status_year_index');
        });

        Schema::create('annual_schedule_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('annual_schedule_id')->constrained('annual_schedules')->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('date_label', 255)->nullable();
            $table->text('title')->nullable();
            $table->string('eligibility', 255)->nullable();
            $table->string('region', 100)->nullable();
            $table->text('venue')->nullable();
            $table->string('point_mark', 10)->nullable();
            $table->string('average_mark', 10)->nullable();
            $table->string('prize_mark', 10)->nullable();
            $table->string('title_mark', 10)->nullable();
            $table->text('note')->nullable();
            $table->string('row_type', 20)->default('event');
            $table->string('source_type', 30)->default('manual');
            $table->timestamps();

            $table->unique('tournament_id', 'annual_schedule_rows_tournament_unique');
            $table->index(
                ['annual_schedule_id', 'month', 'sort_order'],
                'annual_schedule_rows_schedule_month_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_schedule_rows');
        Schema::dropIfExists('annual_schedules');
    }
};
