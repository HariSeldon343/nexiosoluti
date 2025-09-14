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
        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['owner', 'manager', 'member'])->default('member'); // Ruolo in azienda
            $table->boolean('is_primary')->default(false); // Azienda primaria per l'utente
            $table->date('joined_at')->nullable(); // Data ingresso
            $table->date('left_at')->nullable(); // Data uscita
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indici per performance
            $table->unique(['company_id', 'user_id']); // Un utente per azienda
            $table->index('tenant_id');
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'is_primary']);
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('company_users');
    }
};