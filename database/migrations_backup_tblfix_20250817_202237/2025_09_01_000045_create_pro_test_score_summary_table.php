<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_score_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_test_id');

            $table->integer('total_score')->nullable()->comment('合計スコア');
            $table->decimal('average_score', 5, 2)->nullable()->comment('アベレージ');
            $table->boolean('passed_flag')->default(false)->comment('通過フラグ');

            $table->text('remarks')->nullable()->comment('備考');

            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_score_summary');
    }
};

