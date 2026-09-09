<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_archives', function (Blueprint $table): void {
            $table->string('classification', 32)->default('official_tournament')->index();
            $table->string('venue_name')->nullable();
            $table->string('organizer_name')->nullable();
            $table->string('approval_number', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_archives', function (Blueprint $table): void {
            $table->dropIndex(['classification']);
            $table->dropColumn(['classification', 'venue_name', 'organizer_name', 'approval_number']);
        });
    }
};
