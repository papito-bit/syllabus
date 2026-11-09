<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcours extends Model
{
    use HasPublicId;

    protected $table = 'parcours';

    protected $fillable = ['code', 'nom', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'niveau_parcours', 'parcours_id', 'niveau_id')
            ->withTimestamps();
    }

    public function maquettes(): HasMany
    {
        return $this->hasMany(Maquette::class, 'parcours_id');
    }
}
