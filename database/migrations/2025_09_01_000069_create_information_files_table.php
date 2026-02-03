<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('information_files')) {
            Schema::create('information_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('information_id');
                $table->string('type', 32);               // pdf / image / custom など（tournament_files と同思想）
                $table->string('title')->nullable();      // 任意（表示名）
                $table->string('file_path');              // storageパス
                $table->string('visibility', 16)->default('public'); // public / members
                $table->unsignedInteger('sort_order')->default(0);

                $table->timestamps();

                $table->foreign('information_id')
                    ->references('id')->on('informations')
                    ->onDelete('cascade');

                $table->index(['information_id', 'sort_order']);
                $table->index('type');
                $table->index('visibility');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('information_files');
    }
};
