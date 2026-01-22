<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_mailouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $t->foreignId('sender_user_id')->constrained('users');
            $t->string('subject');
            $t->text('body');               // HTML or テキスト（そのまま）
            $t->string('from_address')->nullable();
            $t->string('from_name')->nullable();
            $t->string('status')->default('draft'); // draft|sending|sent|failed
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('fail_count')->default(0);
            $t->timestamps();
        });

        Schema::create('group_mail_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mailout_id')->constrained('group_mailouts')->cascadeOnDelete();
            $t->foreignId('pro_bowler_id')->constrained('pro_bowlers');
            $t->string('email');
            $t->string('status')->default('queued'); // queued|sent|failed|skipped
            $t->timestamp('sent_at')->nullable();
            $t->text('error_message')->nullable();
            $t->timestamps();
            $t->index(['mailout_id','status']);
            $t->index(['pro_bowler_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_mail_recipients');
        Schema::dropIfExists('group_mailouts');
    }
};
