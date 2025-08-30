<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('informations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_public')->default(true);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // 誰向けか
            $table->enum('audience', ['public','members','district_leaders','needs_training'])
                  ->default('public');

            // 「未受講者向け」の対象講習（あれば）
            $table->unsignedBigInteger('required_training_id')->nullable();

            $table->timestamps();

            $table->index(['is_public','audience']);
            $table->index(['starts_at','ends_at']);
            $table->index('required_training_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informations');
    }
};
