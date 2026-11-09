<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unites_enseignement', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Supprimer un brouillon de maquette supprime ses UE
            $table->foreignId('maquette_id')->constrained('maquettes')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->unsignedSmallInteger('ordre')->default(0);
            // Nullables : toutes les maquettes officielles n'ont pas forcément ces colonnes
            $table->decimal('credits', 5, 1)->nullable();
            $table->decimal('coefficient', 5, 2)->nullable();
            $table->json('donnees_supplementaires')->nullable();
            $table->timestamps();

            $table->unique(['maquette_id', 'semestre_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unites_enseignement');
    }
};
