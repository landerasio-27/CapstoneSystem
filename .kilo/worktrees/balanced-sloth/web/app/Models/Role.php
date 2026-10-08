<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'display_name', 'description'])]
class Role extends Model
{
    public const ADMIN = 'admin';
    public const INSPECTION_STAFF = 'inspection_staff';

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
