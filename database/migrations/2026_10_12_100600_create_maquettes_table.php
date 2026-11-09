<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maquettes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // restrictOnDelete : on ne supprime jamais un niveau, un parcours
            // ou une année qui possède des maquettes
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('parcours_id')->constrained('parcours')->restrictOnDelete();
            $table->foreignId('annee_universitaire_id')
                ->constrained('annees_universitaires')->restrictOnDelete();

            $table->unsignedSmallInteger('version')->default(1);
            $table->string('titre')->nullable();
            // brouillon | publie | archive
            $table->string('statut', 20)->default('brouillon')->index();
            $table->text('notes')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Nom court : le nom généré automatiquement dépasserait 64 caractères
            $table->unique(
                ['niveau_id', 'parcours_id', 'annee_universitaire_id', 'version'],
                'maquettes_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquettes');
    }
};
