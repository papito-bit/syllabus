<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UniteEnseignement extends Model
{
    use HasPublicId;

    protected $table = 'unites_enseignement';

    protected $fillable = [
        'maquette_id', 'semestre_id', 'code', 'intitule', 'ordre',
        'credits', 'coefficient', 'donnees_supplementaires',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:1',
            'coefficient' => 'decimal:2',
            'donnees_supplementaires' => 'array',
        ];
    }

    public function maquette(): BelongsTo
    {
        return $this->belongsTo(Maquette::class, 'maquette_id');
    }

    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class, 'semestre_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneMaquette::class, 'ue_id')->orderBy('ordre');
    }
}
