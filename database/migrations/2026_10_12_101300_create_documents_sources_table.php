<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_sources', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('nom_original');
            $table->string('chemin_prive', 500);      // chemin dans le stockage privé
            $table->string('mime', 150);
            $table->string('extension', 10);
            $table->unsignedBigInteger('taille');     // en octets
            // Empreinte du fichier : sert à détecter qu'un même document est importé deux fois
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_sources');
    }
};
