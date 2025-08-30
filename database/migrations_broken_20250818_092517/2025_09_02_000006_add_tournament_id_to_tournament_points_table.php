<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::table('tournament_points', function (Blueprint $table) {
        //     $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
        // });
    }

    public function down(): void
    {
        Schema::table('tournament_points', function (Blueprint $table) {
            //
        });
    }
};

