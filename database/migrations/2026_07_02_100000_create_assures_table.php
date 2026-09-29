<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assures', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('sexe', 20)->nullable();
            $table->string('matricule')->unique();
            $table->string('numero_informatique')->nullable();
            $table->string('grade')->nullable();
            $table->string('categorie')->nullable();
            $table->string('numero_cim');
            $table->string('numero_cama');
            $table->string('numero_iup')->nullable();
            $table->string('armee')->nullable();
            $table->string('region')->nullable();
            $table->string('corps')->nullable();
            $table->string('service')->nullable();
            $table->string('section')->nullable();
            $table->string('sous_section')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->unique();
            $table->string('personne_a_prevenir')->nullable();
            $table->string('tel_personne_a_prevenir')->nullable();
            $table->string('password');
            $table->string('statut')->default('en_attente_validation');
            $table->boolean('deux_fa_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assures');
    }
};
