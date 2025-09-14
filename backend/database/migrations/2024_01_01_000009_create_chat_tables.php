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
        // Tabella stanze chat
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['private', 'group', 'channel'])->default('group');
            $table->unsignedBigInteger('company_id')->nullable(); // Stanza aziendale
            $table->unsignedBigInteger('created_by'); // Creatore stanza
            $table->string('avatar_path')->nullable(); // Avatar gruppo
            $table->boolean('is_archived')->default(false); // Archiviata
            $table->json('settings')->nullable(); // Impostazioni stanza
            $table->timestamp('last_message_at')->nullable(); // Ultimo messaggio
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'is_archived']);
            $table->index('last_message_at');
        });

        // Tabella partecipanti stanze
        Schema::create('room_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');
            $table->boolean('is_muted')->default(false); // Notifiche silenziate
            $table->timestamp('last_read_at')->nullable(); // Ultimo messaggio letto
            $table->integer('unread_count')->default(0); // Messaggi non letti
            $table->timestamp('joined_at')->useCurrent(); // Data ingresso
            $table->timestamp('left_at')->nullable(); // Data uscita
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indici per performance
            $table->unique(['room_id', 'user_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'room_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'unread_count']);
        });

        // Tabella messaggi
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('user_id'); // Mittente
            $table->unsignedBigInteger('reply_to_id')->nullable(); // Risposta a messaggio
            $table->text('content'); // Contenuto messaggio
            $table->enum('type', ['text', 'file', 'image', 'system'])->default('text');
            $table->json('attachments')->nullable(); // Allegati
            $table->boolean('is_edited')->default(false); // Modificato
            $table->timestamp('edited_at')->nullable(); // Data modifica
            $table->boolean('is_deleted')->default(false); // Cancellato
            $table->timestamp('deleted_at')->nullable(); // Data cancellazione
            $table->json('reactions')->nullable(); // Reazioni [{user_id: 1, emoji: '👍'}]
            $table->json('mentions')->nullable(); // Menzioni utenti
            $table->json('read_by')->nullable(); // Letto da [{user_id: 1, read_at: '...'}]
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reply_to_id')->references('id')->on('messages')->onDelete('set null');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'room_id']);
            $table->index(['tenant_id', 'room_id', 'created_at']); // Query messaggi cronologici
            $table->index(['tenant_id', 'user_id']);
            $table->index('created_at');
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('room_users');
        Schema::dropIfExists('rooms');
    }
};