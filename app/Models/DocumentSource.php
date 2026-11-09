<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSource extends Model
{
    use HasPublicId;

    protected $table = 'documents_sources';

    protected $fillable = [
        'nom_original', 'chemin_prive', 'mime', 'extension',
        'taille', 'sha256', 'uploaded_by',
    ];

    // Le chemin de stockage ne sort jamais dans l'API
    protected $hidden = ['chemin_prive'];

    public function importations(): HasMany
    {
        return $this->hasMany(Importation::class, 'document_source_id');
    }

    public function televersePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
