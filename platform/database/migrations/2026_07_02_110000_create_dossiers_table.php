<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assure_id')->constrained('assures')->cascadeOnDelete();
            $table->string('ref')->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('lien');
            $table->string('sexe')->nullable();
            $table->string('statut');
            $table->string('gestionnaire')->nullable();
            $table->date('date_soumission')->nullable();
            $table->date('date_decision')->nullable();
            $table->text('motif_refus')->nullable();
            $table->json('journal')->nullable();
            $table->timestamps();
        });

        Schema::create('assure_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assure_id')->constrained('assures')->cascadeOnDelete();
            $table->string('type');
            $table->string('titre');
            $table->text('contenu');
            $table->string('lien')->nullable();
            $table->boolean('lu')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assure_notifications');
        Schema::dropIfExists('dossiers');
    }
};
