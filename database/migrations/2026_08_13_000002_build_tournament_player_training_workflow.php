<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('trainings')->updateOrInsert(
            ['code' => 'mandatory'],
            [
                'name' => 'トーナメントプレイヤー講習会',
                'valid_for_months' => 36,
                'mandatory' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Schema::create('training_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->unsignedSmallInteger('session_year');
            $table->string('name');
            $table->date('held_on');
            $table->string('venue')->nullable();
            $table->string('status', 20)->default('planning');
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['session_year', 'held_on'], 'training_sessions_year_held_on_index');
            $table->index(['training_id', 'status'], 'training_sessions_training_status_index');
        });

        Schema::table('pro_bowler_trainings', function (Blueprint $table): void {
            $table->foreignId('training_session_id')
                ->nullable()
                ->after('training_id')
                ->constrained('training_sessions')
                ->nullOnDelete();
            $table->string('record_status', 20)->default('valid')->after('expires_at');
            $table->timestamp('revoked_at')->nullable()->after('record_status');
            $table->foreignId('recorded_by_user_id')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['training_id', 'record_status', 'expires_at'],
                'pro_bowler_trainings_status_expiry_index'
            );
        });

        Schema::create('training_session_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->string('attendance_status', 20)->default('registered');
            $table->text('notes')->nullable();
            $table->foreignId('pro_bowler_training_id')
                ->nullable()
                ->constrained('pro_bowler_trainings')
                ->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['training_session_id', 'pro_bowler_id'],
                'training_session_participants_session_bowler_unique'
            );
            $table->index(
                ['training_session_id', 'attendance_status'],
                'training_session_participants_attendance_index'
            );
        });

        Schema::table('pro_bowlers', function (Blueprint $table): void {
            $table->string('training_compliance_status', 30)
                ->default('unconfirmed')
                ->after('can_enter_official_tournament');
            $table->timestamp('training_compliance_checked_at')
                ->nullable()
                ->after('training_compliance_status');

            $table->index(
                ['training_compliance_status', 'is_active'],
                'pro_bowlers_training_compliance_active_index'
            );
        });

        Schema::create('training_compliance_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->foreignId('pro_bowler_training_id')
                ->nullable()
                ->constrained('pro_bowler_trainings')
                ->nullOnDelete();
            $table->string('notification_type', 40)->default('expiry_previous_year');
            $table->date('expires_on');
            $table->unsignedSmallInteger('notice_year');
            $table->string('recipient_email')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['pro_bowler_id', 'expires_on', 'notification_type'],
                'training_compliance_notifications_bowler_expiry_type_unique'
            );
            $table->index(
                ['notice_year', 'status'],
                'training_compliance_notifications_year_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_compliance_notifications');

        Schema::table('pro_bowlers', function (Blueprint $table): void {
            $table->dropIndex('pro_bowlers_training_compliance_active_index');
            $table->dropColumn(['training_compliance_status', 'training_compliance_checked_at']);
        });

        Schema::dropIfExists('training_session_participants');

        Schema::table('pro_bowler_trainings', function (Blueprint $table): void {
            $table->dropIndex('pro_bowler_trainings_status_expiry_index');
            $table->dropConstrainedForeignId('recorded_by_user_id');
            $table->dropColumn(['record_status', 'revoked_at']);
            $table->dropConstrainedForeignId('training_session_id');
        });

        Schema::dropIfExists('training_sessions');
    }
};
