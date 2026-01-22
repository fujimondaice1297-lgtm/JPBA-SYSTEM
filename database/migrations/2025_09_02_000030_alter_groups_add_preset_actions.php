<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $t) {
            $t->string('preset')->nullable()->after('key'); // UIで選んだテンプレ名
            $t->boolean('action_mypage')->default(false)->after('show_on_mypage');
            $t->boolean('action_email')->default(false);
            $t->boolean('action_postal')->default(false);
        });
    }
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $t) {
            $t->dropColumn(['preset','action_mypage','action_email','action_postal']);
        });
    }
};
