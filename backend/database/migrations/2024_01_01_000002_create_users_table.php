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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('two_factor_secret')->nullable(); // Segreto per 2FA
            $table->text('two_factor_recovery_codes')->nullable(); // Codici di recupero 2FA
            $table->timestamp('two_factor_confirmed_at')->nullable(); // Conferma 2FA
            $table->enum('role', ['admin', 'special_user', 'user'])->default('user');
            $table->boolean('is_company_referent')->default(false); // Referente azienda
            $table->boolean('can_access_multiple_tenants')->default(false); // Accesso multi-tenant
            $table->string('avatar_path')->nullable(); // Path avatar
            $table->string('phone')->nullable(); // Telefono
            $table->string('job_title')->nullable(); // Titolo lavorativo
            $table->json('preferences')->nullable(); // Preferenze utente JSON
            $table->string('timezone')->default('Europe/Rome'); // Fuso orario
            $table->string('locale')->default('it'); // Lingua
            $table->boolean('is_active')->default(true); // Stato attivo
            $table->timestamp('last_login_at')->nullable(); // Ultimo accesso
            $table->string('last_login_ip')->nullable(); // IP ultimo accesso
            $table->integer('failed_login_attempts')->default(0); // Tentativi login falliti
            $table->timestamp('locked_until')->nullable(); // Blocco temporaneo account
            $table->rememberToken();
            $table->timestamps();

            // Chiave esterna
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indici per performance
            $table->unique(['tenant_id', 'email']); // Email unica per tenant
            $table->index('tenant_id');
            $table->index('email');
            $table->index('role');
            $table->index('is_active');
            $table->index(['tenant_id', 'is_active']); // Query comuni
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};