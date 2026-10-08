<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'document_no', 'type', 'status', 'parent_document_id', 'source_type', 'source_id',
    // Filing: set when the document is moved into an archive year.
    'archive_id', 'archived_at',
    'teacher_id', 'room_id', 'prepared_by', 'approved_by', 'issued_by',
    'custodian', 'custodian_position',
    'entity_name', 'fund_cluster', 'document_date', 'remarks', 'approved_at', 'issued_at',
    // IAR-specific
    'supplier', 'po_no', 'po_date', 'invoice_no', 'invoice_date',
    'requisition_office', 'requisition_center_code', 'date_inspected', 'date_received', 'acceptance_status',
    // Signatory on the Inspection and Acceptance Report's "Inspection Officer" line
    'inspection_officer',
    // RIS-specific
    'division', 'office', 'responsibility_center_code', 'purpose',
    // Free-text signatory overrides (PAR/ICS/RIS)
    'requested_by', 'received_by',
])]
class Document extends Model
{
    use Archivable;

    public const TYPES = ['PAR', 'IAR', 'ICS', 'RIS'];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'archived_at' => 'datetime',
            'po_date' => 'date',
            'invoice_date' => 'date',
            'date_inspected' => 'date',
            'date_received' => 'date',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_document_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Document::class, 'parent_document_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * The document's custodian: the person chosen on the form, falling back to
     * a name typed into the legacy free-text column for records saved while
     * that field existed.
     */
    public function getCustodianNameAttribute(): string
    {
        return (string) ($this->teacher?->full_name ?: $this->custodian);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    /**
     * Generate the next auto-number for a document type, e.g. PAR-2026-00001.
     *
     * Used when the officer leaves the Document No. field blank on the form.
     */
    public static function nextDocumentNumber(string $type): string
    {
        return self::nextSequenceNumber($type, lock: true);
    }

    /**
     * What the form shows as the suggested number (a placeholder, never a
     * value that is submitted): the same figure nextDocumentNumber() would
     * assign, read without a row lock so opening the form costs nothing.
     */
    public static function suggestedDocumentNumber(string $type): string
    {
        return self::nextSequenceNumber($type, lock: false);
    }

    /**
     * The next free number for a type in the current year: one past the highest
     * sequence already used.
     *
     * Deliberately not a row count: a number typed in by hand (or a deleted
     * draft) would otherwise leave a count that points at a number already on
     * file and the create would fail on the unique index. Walking the existing
     * numbers also means a gap left by an older record is skipped rather than
     * reused.
     */
    private static function nextSequenceNumber(string $type, bool $lock): string
    {
        $prefix = sprintf('%s-%d-', $type, now()->year);

        $query = self::query()
            ->where('type', $type)
            ->where('document_no', 'like', $prefix.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $highest = $query->pluck('document_no')
            ->map(fn ($number) => (int) substr((string) $number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.sprintf('%05d', $highest + 1);
    }
}
