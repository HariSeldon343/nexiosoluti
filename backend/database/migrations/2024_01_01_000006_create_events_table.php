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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('calendar_id');
            $table->unsignedBigInteger('user_id'); // Creatore evento
            $table->unsignedBigInteger('company_id')->nullable(); // Evento aziendale
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable(); // Luogo
            $table->dateTime('start_at'); // Inizio evento
            $table->dateTime('end_at'); // Fine evento
            $table->boolean('all_day')->default(false); // Evento tutto il giorno
            $table->string('color', 7)->nullable(); // Colore personalizzato
            $table->enum('status', ['confirmed', 'tentative', 'cancelled'])->default('confirmed');
            $table->enum('visibility', ['public', 'private', 'confidential'])->default('public');
            $table->boolean('is_recurring')->default(false); // Evento ricorrente
            $table->json('recurrence_rule')->nullable(); // Regole ricorrenza (RRULE)
            $table->unsignedBigInteger('parent_event_id')->nullable(); // ID evento padre per ricorrenze
            $table->date('recurrence_date')->nullable(); // Data specifica per istanza ricorrente
            $table->json('reminders')->nullable(); // Promemoria [{minutes: 15, type: 'email'}]
            $table->string('meeting_url')->nullable(); // Link videoconferenza
            $table->json('attachments')->nullable(); // File allegati
            $table->string('caldav_uid')->unique()->nullable(); // UID per CalDAV
            $table->integer('sequence')->default(0); // Versione evento per CalDAV
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('calendar_id')->references('id')->on('calendars')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('parent_event_id')->references('id')->on('events')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'calendar_id']);
            $table->index(['tenant_id', 'start_at', 'end_at']); // Query su intervalli
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'is_recurring']);
            $table->index('parent_event_id');
            $table->index('caldav_uid');
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};