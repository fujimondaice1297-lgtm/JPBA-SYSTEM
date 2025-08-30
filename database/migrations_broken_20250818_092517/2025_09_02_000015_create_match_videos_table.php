<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class creatematchvideostable extends Migration {
    public function up(): void
    {
        Schema::create('match_videos', function (Blueprint $table) {
            $table->id();

            // 外部キー（match_scores.id と連携）
            //$table->foreignId('match_score_id')->constrained('match_scores')->onDelete('cascade');

            // 動画情報
            $table->text('video_url');               // 動画URL
            $table->text('description')->nullable(); // 補足説明（任意）

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_videos');
    }
};

