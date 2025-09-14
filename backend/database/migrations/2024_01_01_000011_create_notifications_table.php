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
        // Tabella notifiche
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->string('type'); // Tipo notifica (App\Notifications\...)
            $table->morphs('notifiable'); // Polimorfico (user, etc.)
            $table->text('data'); // Dati notifica JSON
            $table->timestamp('read_at')->nullable(); // Letta
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'notifiable_type', 'notifiable_id']);
            $table->index(['tenant_id', 'read_at']);
            $table->index('created_at');
        });

        // Tabella subscription push notifications
        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('endpoint', 500); // Endpoint push
            $table->string('public_key')->nullable(); // Chiave pubblica
            $table->string('auth_token')->nullable(); // Token autenticazione
            $table->string('content_encoding')->nullable(); // Encoding
            $table->string('device_type')->nullable(); // Tipo dispositivo
            $table->string('browser')->nullable(); // Browser
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'is_active']);
            $table->unique('endpoint');
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_subscriptions');
        Schema::dropIfExists('notifications');
    }
};