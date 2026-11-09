<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueModification extends Model
{
    protected $table = 'historique_modifications';

    // Journal en ajout seul : pas de colonne updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'entite_type', 'entite_id', 'entite_public_id',
        'action', 'description', 'anciennes_valeurs', 'nouvelles_valeurs', 'ip_address',
    ];

    // L'id interne de l'entité ne sort jamais dans l'API
    protected $hidden = ['id', 'entite_id'];

    protected function casts(): array
    {
        return [
            'anciennes_valeurs' => 'array',
            'nouvelles_valeurs' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
