<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_patterns', function (Blueprint $table) {
            $table->id();
            // Phase A は最小構成。詳細列（name/type 等）は Phase B の add_* で追加
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_patterns');
    }
};
