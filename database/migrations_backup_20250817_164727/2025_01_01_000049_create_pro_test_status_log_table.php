<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_status_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_test_id')->constrained('pro_test');

            $table->string('status_code')->comment('ステータス（例：書類審査通過）');
            $table->text('memo')->nullable()->comment('補足メモ');

            $table->timestamp('changed_at')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_status_log');
    }
};
