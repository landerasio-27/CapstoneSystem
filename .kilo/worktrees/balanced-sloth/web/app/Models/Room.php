<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'building', 'floor', 'type', 'is_active'])]
class Room extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }
}
