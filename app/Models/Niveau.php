<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Niveau extends Model
{
    use HasPublicId;

    protected $table = 'niveaux';

    protected $fillable = ['code', 'libelle', 'ordre'];

    public function parcours(): BelongsToMany
    {
        return $this->belongsToMany(Parcours::class, 'niveau_parcours', 'niveau_id', 'parcours_id')
            ->withTimestamps();
    }

    public function maquettes(): HasMany
    {
        return $this->hasMany(Maquette::class, 'niveau_id');
    }
}
