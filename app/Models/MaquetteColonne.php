<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaquetteColonne extends Model
{
    protected $table = 'maquette_colonnes';

    protected $fillable = ['maquette_id', 'cle', 'libelle', 'ordre'];

    public function maquette(): BelongsTo
    {
        return $this->belongsTo(Maquette::class, 'maquette_id');
    }
}
