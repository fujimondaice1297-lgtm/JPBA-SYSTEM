<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_result_status', function (Blueprint $table) {
            $table->id(); // 主キー
            $table->string('status')->comment('合否区分（合格、不合格、辞退など）');
            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_result_status');
    }
};
