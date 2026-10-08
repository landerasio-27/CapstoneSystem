<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An archive year — the container the office files old records under.
 *
 * The year itself holds no records: it is the label documents and cards point
 * at through their archive_id. Adding a year is how a person says "file things
 * under 2025". A year is never deleted, so a record can never lose the year it
 * was filed under.
 */
#[Fillable(['year', 'notes', 'created_by'])]
class Archive extends Model
{
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    /** PAR / ICS / RIS documents filed under this year. */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** Stock Cards (SC) and Property Cards (PC) filed under this year. */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
