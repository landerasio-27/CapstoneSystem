<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A custodian (a teacher or an office staff member) and, seen as a whole, their
 * folder: the page under Organization → Custodians is a view over this record
 * plus the documents and equipment that name it. Nothing about the folder is
 * stored: assign a PAR/ICS/RIS or a piece of equipment to this person anywhere
 * in the system and it is in the folder the moment it is saved.
 */
#[Fillable([
    'employee_id', 'first_name', 'last_name', 'department', 'position',
    'email', 'contact_number', 'room_id', 'is_active',
])]
class Teacher extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * The next number in the office's own series: FAC-0001, FAC-0002 …
     *
     * A folder is filed against a person, so the number belongs to the record
     * rather than to the folder: it is assigned once when the record is created
     * and is never typed on a form. Anything not in the series is ignored when
     * the next one is worked out.
     */
    public static function nextEmployeeId(): string
    {
        $highest = static::query()
            ->pluck('employee_id')
            ->map(fn (?string $id) => preg_match('/^FAC-(\d+)$/', (string) $id, $matches) ? (int) $matches[1] : 0)
            ->max();

        return 'FAC-'.str_pad((string) (($highest ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * When anything in the folder last changed: the record itself, or the
     * newest document or piece of equipment that names it. Read off the
     * `latest_document_at` / `latest_equipment_at` columns the folder grid
     * selects alongside the record, and off the relations when they are not there.
     */
    public function lastActivityAt(): Carbon
    {
        $moments = [
            $this->updated_at,
            $this->getAttribute('latest_document_at'),
            $this->getAttribute('latest_equipment_at'),
        ];

        if (! $this->exists) {
            return now();
        }

        $dates = collect($moments)
            ->filter()
            ->map(fn ($at) => $at instanceof Carbon ? $at : Carbon::parse($at));

        return $dates->max() ?? $this->created_at ?? now();
    }
}
