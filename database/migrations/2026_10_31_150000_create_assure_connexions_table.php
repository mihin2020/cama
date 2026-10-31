<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Historique réel des connexions à l'espace assuré (affiché dans « Dernières connexions »). */
    public function up(): void
    {
        Schema::create('assure_connexions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assure_id')->constrained('assures')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('appareil');
            $table->boolean('inhabituelle')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['assure_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assure_connexions');
    }
};
