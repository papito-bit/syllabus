<?php

namespace App\Models;

use App\Enums\StatutImportation;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Importation extends Model
{
    use HasPublicId;

    protected $table = 'importations';

    protected $fillable = [
        'document_source_id', 'statut', 'donnees_extraites', 'donnees_corrigees',
        'avertissements', 'maquette_id', 'created_by', 'validated_by', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutImportation::class,
            'donnees_extraites' => 'array',
            'donnees_corrigees' => 'array',
            'avertissements' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function documentSource(): BelongsTo
    {
        return $this->belongsTo(DocumentSource::class, 'document_source_id');
    }

    public function maquette(): BelongsTo
    {
        return $this->belongsTo(Maquette::class, 'maquette_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
