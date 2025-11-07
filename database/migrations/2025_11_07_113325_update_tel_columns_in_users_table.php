<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer les contraintes uniques existantes
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tel_user']);
            $table->dropUnique(['autre_tel']);
        });

        // Recréer les colonnes sans unique
        Schema::table('users', function (Blueprint $table) {
            $table->string('tel_user')->nullable()->change();
            $table->string('autre_tel')->nullable()->change();
        });
    }

    public function down(): void
    {
        // En cas de rollback : rétablir les contraintes uniques
        Schema::table('users', function (Blueprint $table) {
            $table->string('tel_user')->unique()->nullable()->change();
            $table->string('autre_tel')->unique()->nullable()->change();
        });
    }
};
