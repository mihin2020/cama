<?php

use App\Enums\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles des comptes internes gérés en base : en plus des 4 rôles historiques (rôles « système »),
 * un administrateur peut créer ses propres rôles (ex. webmaster) avec leurs permissions.
 * admin_users.role contient désormais le slug d'un rôle de cette table.
 */
return new class extends Migration
{
    /** Rang hiérarchique : on ne gère que les comptes d'un rang inférieur ou égal au sien. */
    private const SYSTEM_ROLES = [
        'gestionnaire' => ['rang' => 10, 'description' => 'Instruit les dossiers qui lui sont affectés.'],
        'superviseur' => ['rang' => 50, 'description' => 'Encadre les gestionnaires, affecte et supervise les dossiers.'],
        'direction' => ['rang' => 70, 'description' => 'Consultation des indicateurs et des dossiers (lecture seule).'],
        'administrateur' => ['rang' => 100, 'description' => 'Accès complet à la plateforme, y compris les rôles et les comptes.'],
    ];

    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('nom', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('rang')->default(10);
            $table->boolean('systeme')->default(false);
            $table->boolean('actif')->default(true);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (self::SYSTEM_ROLES as $slug => $role) {
            $enum = AdminRole::from($slug);

            DB::table('admin_roles')->insert([
                'slug' => $slug,
                'nom' => $enum->label(),
                'description' => $role['description'],
                'rang' => $role['rang'],
                'systeme' => true,
                'actif' => true,
                'permissions' => json_encode(AdminPermissions::presetForRole($enum)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Un compte dont le rôle n'existe pas (donnée corrompue) devient gestionnaire, le rôle le
        // moins privilégié, avant de poser la contrainte d'intégrité.
        DB::table('admin_users')
            ->whereNotIn('role', array_keys(self::SYSTEM_ROLES))
            ->update(['role' => 'gestionnaire']);

        Schema::table('admin_users', function (Blueprint $table) {
            $table->foreign('role')->references('slug')->on('admin_roles')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropForeign(['role']);
        });

        Schema::dropIfExists('admin_roles');
    }
};
