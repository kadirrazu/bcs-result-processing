<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'exam';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('registration_audits')) {
            return;
        }

        $schema->create('registration_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->string('action', 80);
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name')->nullable();
            $table->text('reason');
            $table->json('changed_fields');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('registration_id', 'reg_audit_registration_idx');
            $table->index('actor_id', 'reg_audit_actor_idx');
            $table->index('action', 'reg_audit_action_idx');
            $table->index('created_at', 'reg_audit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('registration_audits');
    }
};
