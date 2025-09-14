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
        // Tabella cartelle
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('parent_id')->nullable(); // Cartella padre
            $table->unsignedBigInteger('user_id'); // Proprietario
            $table->unsignedBigInteger('company_id')->nullable(); // Cartella aziendale
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('path'); // Path completo
            $table->string('color', 7)->nullable(); // Colore cartella
            $table->boolean('is_shared')->default(false); // Condivisa
            $table->json('share_settings')->nullable(); // Impostazioni condivisione
            $table->boolean('is_system')->default(false); // Cartella di sistema
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'path']);
            $table->index(['tenant_id', 'is_shared']);
        });

        // Tabella files
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('folder_id')->nullable(); // Cartella contenitore
            $table->unsignedBigInteger('user_id'); // Proprietario/uploader
            $table->unsignedBigInteger('company_id')->nullable(); // File aziendale
            $table->string('name');
            $table->string('original_name'); // Nome originale
            $table->text('description')->nullable();
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->bigInteger('size'); // Dimensione in bytes
            $table->string('path'); // Path fisico
            $table->string('hash')->nullable(); // Hash SHA256 per deduplicazione
            $table->boolean('is_shared')->default(false);
            $table->json('share_settings')->nullable(); // Impostazioni condivisione
            $table->boolean('requires_approval')->default(false); // Richiede approvazione
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->integer('version')->default(1); // Numero versione
            $table->unsignedBigInteger('current_version_id')->nullable(); // ID versione corrente
            $table->json('metadata')->nullable(); // Metadati aggiuntivi
            $table->integer('download_count')->default(0); // Contatore download
            $table->dateTime('last_accessed_at')->nullable(); // Ultimo accesso
            $table->boolean('is_locked')->default(false); // File bloccato per modifica
            $table->unsignedBigInteger('locked_by')->nullable(); // Chi ha bloccato
            $table->dateTime('locked_at')->nullable(); // Quando è stato bloccato
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'folder_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'mime_type']);
            $table->index(['tenant_id', 'requires_approval']);
            $table->index(['tenant_id', 'hash']); // Per deduplicazione
            $table->index('created_at');
        });

        // Tabella versioni file
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('file_id');
            $table->unsignedBigInteger('user_id'); // Chi ha caricato la versione
            $table->integer('version_number');
            $table->string('path'); // Path versione
            $table->bigInteger('size');
            $table->string('hash')->nullable();
            $table->text('change_notes')->nullable(); // Note modifiche
            $table->boolean('is_current')->default(false); // Versione corrente
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'file_id']);
            $table->index(['tenant_id', 'file_id', 'is_current']);
            $table->unique(['file_id', 'version_number']);
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('files');
        Schema::dropIfExists('folders');
    }
};