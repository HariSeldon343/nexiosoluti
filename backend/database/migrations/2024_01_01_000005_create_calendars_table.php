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
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id'); // Proprietario calendario
            $table->unsignedBigInteger('company_id')->nullable(); // Calendario aziendale
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3B82F6'); // Colore hex
            $table->string('timezone')->default('Europe/Rome');
            $table->boolean('is_public')->default(false); // Visibile a tutti nel tenant
            $table->boolean('is_default')->default(false); // Calendario predefinito
            $table->string('caldav_uri')->unique()->nullable(); // URI per CalDAV
            $table->json('sync_settings')->nullable(); // Impostazioni sincronizzazione
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'is_public']);
            $table->index('caldav_uri');
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};