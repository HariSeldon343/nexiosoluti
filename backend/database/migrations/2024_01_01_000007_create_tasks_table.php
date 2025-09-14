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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id'); // Creatore task
            $table->unsignedBigInteger('company_id')->nullable(); // Task aziendale
            $table->unsignedBigInteger('project_id')->nullable(); // Progetto di appartenenza
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('due_date')->nullable(); // Scadenza
            $table->date('start_date')->nullable(); // Data inizio
            $table->dateTime('completed_at')->nullable(); // Data completamento
            $table->unsignedBigInteger('completed_by')->nullable(); // Chi ha completato
            $table->integer('estimated_hours')->nullable(); // Ore stimate
            $table->integer('actual_hours')->nullable(); // Ore effettive
            $table->boolean('is_recurring')->default(false); // Task ricorrente
            $table->json('recurrence_pattern')->nullable(); // Pattern ricorrenza
            $table->json('tags')->nullable(); // Tags/etichette
            $table->json('checklist')->nullable(); // Checklist items
            $table->integer('checklist_completed')->default(0); // Items completati
            $table->integer('checklist_total')->default(0); // Totale items
            $table->json('attachments')->nullable(); // File allegati
            $table->unsignedBigInteger('parent_task_id')->nullable(); // Task padre
            $table->integer('order_index')->default(0); // Ordinamento
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_task_id')->references('id')->on('tasks')->onDelete('cascade');

            // Indici per performance
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index('parent_task_id');
            $table->index('order_index');
        });
    }

    /**
     * Annulla la migrazione
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};