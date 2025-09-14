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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique()->nullable(); // Dominio personalizzato
            $table->string('subdomain')->unique(); // Sottodominio obbligatorio
            $table->string('logo_path')->nullable(); // Path del logo
            $table->string('favicon_path')->nullable(); // Path della favicon
            $table->string('primary_color', 7)->default('#3B82F6'); // Colore primario hex
            $table->string('secondary_color', 7)->default('#1E40AF'); // Colore secondario hex
            $table->json('settings')->nullable(); // Impostazioni aggiuntive JSON
            $table->boolean('is_active')->default(true); // Stato attivo/inattivo
            $table->string('contact_email')->nullable(); // Email di contatto
            $table->string('contact_phone')->nullable(); // Telefono di contatto
            $table->text('address')->nullable(); // Indirizzo completo
            $table->string('vat_number')->nullable(); // Partita IVA
            $table->integer('max_users')->default(0); // 0 = illimitati
            $table->integer('max_storage_mb')->default(0); // 0 = illimitato
            $table->date('subscription_expires_at')->nullable(); // Scadenza abbonamento
            $table->string('subscription_plan')->default('basic'); // Piano abbonamento
            $table->timestamps();

            // Indici per performance
            $table->index('subdomain');
            $table->index('domain');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};