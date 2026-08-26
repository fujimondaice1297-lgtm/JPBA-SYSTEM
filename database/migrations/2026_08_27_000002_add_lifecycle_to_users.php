<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status')->default('active')->index();
            $table->timestamp('setup_link_sent_at')->nullable();
            $table->timestamp('password_set_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('account_status_note')->nullable();
        });

        DB::statement(
            "ALTER TABLE users
             ADD CONSTRAINT users_account_status_check
             CHECK (account_status IN ('active', 'suspended', 'closed'))"
        );

        Schema::create('user_account_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'user_account_status_logs_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_status_logs');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_status_check');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_status']);
            $table->dropColumn([
                'account_status',
                'setup_link_sent_at',
                'password_set_at',
                'suspended_at',
                'closed_at',
                'account_status_note',
            ]);
        });
    }
};
