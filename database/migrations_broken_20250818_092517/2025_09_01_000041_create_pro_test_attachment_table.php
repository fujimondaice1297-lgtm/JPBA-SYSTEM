<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_attachment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_test_id');

            $table->string('file_path')->comment('保存パス');
            $table->string('file_type')->nullable()->comment('ファイル種別（顔写真など）');
            $table->string('original_file_name')->nullable();
            $table->string('mime_type')->nullable();

            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_attachment');
    }
};


