<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tournament_organizations')) {
            Schema::create('tournament_organizations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('category', 32); // host / special_sponsor / sponsor / support / cooperation
                $table->string('name');
                $table->string('url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);

                $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_organizations');
    }
};
