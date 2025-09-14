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
        // Tabella audit log
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Utente che ha eseguito l'azione
            $table->string('user_name')->nullable(); // Nome utente al momento dell'azione
            $table->string('user_email')->nullable(); // Email utente al momento dell'azione
            $table->string('event'); // Tipo evento (created, updated, deleted, etc.)
            $table->string('auditable_type'); // Tipo modello (App\Models\User, etc.)
            $table->unsignedBigInteger('auditable_id')->nullable(); // ID record
            $table->json('old_values')->nullable(); // Valori precedenti
            $table->json('new_values')->nullable(); // Nuovi valori
            $table->text('url')->nullable(); // URL richiesta
            $table->string('ip_address', 45)->nullable(); // IP client
            $table->string('user_agent')->nullable(); // User agent browser
            $table->string('method', 10)->nullable(); // Metodo HTTP
            $table->json('tags')->nullable(); // Tags aggiuntivi
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'event']);
            $table->index(['tenant_id', 'auditable_type', 'auditable_id']);
            $table->index('created_at');
            $table->index(['tenant_id', 'created_at']); // Query per periodo
        });

        // Tabella summary audit log (per log eliminati)
        Schema::create('audit_log_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('summary_date'); // Data del summary
            $table->string('summary_type'); // Tipo summary (daily, monthly, deletion)
            $table->integer('total_events'); // Totale eventi
            $table->json('events_by_type'); // Conteggio per tipo evento
            $table->json('events_by_user'); // Conteggio per utente
            $table->json('events_by_model'); // Conteggio per modello
            $table->text('notes')->nullable(); // Note aggiuntive
            $table->unsignedBigInteger('created_by')->nullable(); // Chi ha creato il summary
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'summary_date']);
            $table->index(['tenant_id', 'summary_type']);
            $table->unique(['tenant_id', 'summary_date', 'summary_type']);
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log_summaries');
        Schema::dropIfExists('audit_logs');
    }
};