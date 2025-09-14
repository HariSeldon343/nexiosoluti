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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('vat_number')->nullable(); // Partita IVA
            $table->string('tax_code')->nullable(); // Codice fiscale
            $table->string('email')->nullable(); // Email aziendale
            $table->string('phone')->nullable(); // Telefono
            $table->string('website')->nullable(); // Sito web
            $table->text('address')->nullable(); // Indirizzo
            $table->string('city')->nullable(); // Città
            $table->string('postal_code')->nullable(); // CAP
            $table->string('province')->nullable(); // Provincia
            $table->string('country')->default('IT'); // Paese
            $table->json('custom_fields')->nullable(); // Campi personalizzati JSON
            $table->string('logo_path')->nullable(); // Logo aziendale
            $table->text('description')->nullable(); // Descrizione
            $table->boolean('is_active')->default(true); // Stato attivo
            $table->integer('max_users')->default(0); // Limite utenti (0 = illimitato)
            $table->timestamps();

            // Chiave esterna
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'vat_number']); // P.IVA unica per tenant
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};