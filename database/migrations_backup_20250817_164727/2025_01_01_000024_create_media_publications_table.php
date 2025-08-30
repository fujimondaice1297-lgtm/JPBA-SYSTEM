<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_publications', function (Blueprint $table) {
            $table->id();

            // 外部キー（tournaments.id と紐づけ）
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');

            // メディア情報
            $table->string('title');                   // 記事・動画のタイトル
            $table->string('type');                    // 種別（例：記事・動画）
            $table->text('url');                       // 記事または動画へのURL
            $table->date('published_at')->nullable();  // 公開日（任意）

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publications');
    }
};
