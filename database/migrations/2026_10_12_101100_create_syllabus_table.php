<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('ec_id')->constrained('ecs')->restrictOnDelete();
            $table->foreignId('annee_universitaire_id')
                ->constrained('annees_universitaires')->restrictOnDelete();

            // brouillon | publie | archive
            $table->string('statut', 20)->default('brouillon')->index();

            $table->text('objectifs')->nullable();
            $table->text('prerequis')->nullable();
            $table->text('competences')->nullable();
            $table->text('resultats_apprentissage')->nullable();
            $table->longText('programme')->nullable();
            $table->text('methodes_pedagogiques')->nullable();
            $table->text('evaluation')->nullable();
            $table->longText('bibliographie')->nullable();
            // Sections ajoutées librement : [{"titre": "...", "contenu": "..."}]
            $table->json('sections_supplementaires')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un seul syllabus par EC et par année universitaire
            $table->unique(['ec_id', 'annee_universitaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus');
    }
};
