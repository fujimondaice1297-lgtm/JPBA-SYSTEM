<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ball_annual_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_bowler_id')
                ->constrained('pro_bowlers')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('registration_year');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('return_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['pro_bowler_id', 'registration_year', 'revision'],
                'ball_annual_reg_bowler_year_revision_unique'
            );
            $table->index(
                ['registration_year', 'status'],
                'ball_annual_reg_year_status_index'
            );
        });

        Schema::create('ball_annual_registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->constrained('ball_annual_registrations')
                ->cascadeOnDelete();
            $table->foreignId('used_ball_id')
                ->constrained('used_balls')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['registration_id', 'used_ball_id'],
                'ball_annual_reg_items_registration_ball_unique'
            );
            $table->index('used_ball_id', 'ball_annual_reg_items_used_ball_index');
        });

        Schema::create('ball_annual_registration_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->constrained('ball_annual_registrations')
                ->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('acted_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(
                ['registration_id', 'created_at'],
                'ball_annual_reg_history_registration_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_annual_registration_histories');
        Schema::dropIfExists('ball_annual_registration_items');
        Schema::dropIfExists('ball_annual_registrations');
    }
};
