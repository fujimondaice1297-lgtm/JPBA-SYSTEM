<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_entry_balls', function (Blueprint $table) {
            $table->id();
            // まずは nullable（後で締める前提だと安全）
            $table->foreignId('tournament_entry_id')->nullable()
                ->constrained('tournament_entries')->cascadeOnDelete();
            $table->foreignId('used_ball_id')->nullable()
                ->constrained('used_balls')->cascadeOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tournament_entry_balls');
    }

};
