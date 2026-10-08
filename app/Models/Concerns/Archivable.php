<?php

namespace App\Models\Concerns;

use App\Models\Archive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared archiving behaviour for records that can be filed into an archive year
 * (see App\Models\Archive) instead of being deleted, and restored to the
 * working lists at any time.
 *
 * Expects the model to carry an `archive_id` column; `archived_at` records when
 * it was filed away. Nothing else changes: an archived record keeps its
 * quantity, movements and printed documents.
 */
trait Archivable
{
    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function isArchived(): bool
    {
        return $this->archive_id !== null;
    }

    /** The working lists: everything not filed away yet. */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archive_id');
    }
}
