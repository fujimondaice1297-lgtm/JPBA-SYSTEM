<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_venue', function (Blueprint $table) {
            $table->id(); // 主キー
            $table->string('name')->comment('会場名（ボウリング場名など）');
            $table->string('address')->nullable()->comment('住所');
            $table->string('phone')->nullable()->comment('電話番号');
            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_venue');
    }
};
