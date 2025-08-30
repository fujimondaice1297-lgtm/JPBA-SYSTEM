<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_comment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_test_id');

            $table->text('comment')->comment('自由記述のコメント・メモ');
            $table->string('posted_by')->nullable()->comment('投稿者名または管理者ID');
            $table->timestamp('posted_at')->nullable();

            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_comment');
    }
};

