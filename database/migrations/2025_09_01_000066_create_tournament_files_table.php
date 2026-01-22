<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tournament_files')) {
            Schema::create('tournament_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->string('type', 32);     // outline_public / outline_player / oil_pattern / custom
                $table->string('title')->nullable(); // custom の時は必須想定
                $table->string('file_path');    // storageパス
                $table->string('visibility', 16)->default('public'); // public / members
                $table->unsignedInteger('sort_order')->default(0);

                $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_files');
    }
};
