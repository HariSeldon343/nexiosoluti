<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esegue la migrazione
     */
    public function up(): void
    {
        // Tabella gruppi
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Indici
            $table->index('tenant_id');
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Tabella utenti-gruppi
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indici
            $table->unique(['group_id', 'user_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'group_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // Tabella partecipanti eventi
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['accepted', 'declined', 'tentative', 'pending'])->default('pending');
            $table->enum('role', ['organizer', 'required', 'optional'])->default('optional');
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indici
            $table->unique(['event_id', 'user_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'event_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Tabella assegnazioni task
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('cascade');

            // Indici
            $table->unique(['task_id', 'user_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // Tabella occorrenze task (per task non consecutive)
        Schema::create('task_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('task_id');
            $table->date('occurrence_date');
            $table->enum('status', ['pending', 'completed', 'skipped'])->default('pending');
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');

            // Indici
            $table->unique(['task_id', 'occurrence_date']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'occurrence_date']);
            $table->index(['tenant_id', 'status']);
        });

        // Tabella workflow approvazioni
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('model_type'); // Tipo modello (File, Document, etc.)
            $table->json('steps'); // Steps approvazione [{order: 1, approvers: [1,2], type: 'any'}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            // Indici
            $table->index('tenant_id');
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'model_type']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Tabella approvazioni
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('approval_flow_id')->nullable();
            $table->morphs('approvable'); // Polimorfico (file, document, etc.)
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approver_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->integer('step_order')->default(1);
            $table->text('comments')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('approval_flow_id')->references('id')->on('approval_flows')->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');

            // Indici
            $table->index('tenant_id');
            $table->index(['tenant_id', 'approval_flow_id']);
            $table->index(['tenant_id', 'approvable_type', 'approvable_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'approver_id', 'status']);
        });

        // Tabella jobs (per queue)
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        // Tabella failed jobs
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Tabella sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Tabella password resets
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Note: personal_access_tokens table is already created by Laravel Sanctum migration
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        // Note: personal_access_tokens table is managed by Laravel Sanctum
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_flows');
        Schema::dropIfExists('task_occurrences');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};