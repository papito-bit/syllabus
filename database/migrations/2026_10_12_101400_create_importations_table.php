<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // On ne supprime pas un document source qui a servi à une importation
            $table->foreignId('document_source_id')
                ->constrained('documents_sources')->restrictOnDelete();

            // extrait | en_revision | valide | rejete
            $table->string('statut', 20)->default('extrait')->index();
            $table->json('donnees_extraites')->nullable();    // sortie brute de l'extraction
            $table->json('donnees_corrigees')->nullable();    // après correction manuelle
            $table->json('avertissements')->nullable();       // doublons, incohérences...

            $table->foreignId('maquette_id')->nullable()->constrained('maquettes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importations');
    }
};
