<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Génère un identifiant public (ULID) à la création et l'utilise dans les URL.
 * L'identifiant interne "id" n'apparaît jamais dans les sorties JSON.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function initializeHasPublicId(): void
    {
        $this->hidden = array_values(array_unique(array_merge($this->hidden, ['id'])));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
