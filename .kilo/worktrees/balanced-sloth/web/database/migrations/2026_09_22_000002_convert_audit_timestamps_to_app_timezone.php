<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The application used to run on the UTC timezone, so every timestamp that PHP
 * wrote (created_at, updated_at, stock movement dates, approvals, logins, ...)
 * was stored as the UTC wall clock and therefore displayed 8 hours behind the
 * real local time in Cauayan.
 *
 * config/app.php now uses APP_TIMEZONE (Asia/Manila) while storing timestamps
 * as written. This migration re-expresses the rows that already exist so the
 * history keeps telling the truth, instead of only fixing new records.
 *
 * Only PHP generated columns are converted. Columns that hold a value a user
 * typed (inspections.scheduled_at) or a plain calendar date (date columns) are
 * deliberately left alone: they were already local wall clock.
 */
return new class extends Migration
{
    /**
     * Table => PHP written columns, stored as UTC before this migration.
     */
    private const COLUMNS = [
        'activity_logs' => ['created_at', 'updated_at'],
        'device_tokens' => ['last_used_at', 'created_at', 'updated_at'],
        'documents' => ['approved_at', 'issued_at', 'created_at', 'updated_at'],
        'document_items' => ['created_at', 'updated_at'],
        'equipment' => ['created_at', 'updated_at'],
        'inspections' => ['started_at', 'submitted_at', 'completed_at', 'reminder_sent_at', 'created_at', 'updated_at'],
        'inspection_items' => ['scanned_at', 'created_at', 'updated_at'],
        'inventory' => ['created_at', 'updated_at'],
        'notifications' => ['read_at', 'created_at', 'updated_at'],
        'personal_access_tokens' => ['last_used_at', 'created_at', 'updated_at'],
        'qr_codes' => ['printed_at', 'created_at', 'updated_at'],
        'reports' => ['created_at', 'updated_at'],
        'roles' => ['created_at', 'updated_at'],
        'rooms' => ['created_at', 'updated_at'],
        'supplies' => ['created_at', 'updated_at'],
        'supply_requests' => ['approved_at', 'created_at', 'updated_at'],
        'supply_request_items' => ['created_at', 'updated_at'],
        'teachers' => ['created_at', 'updated_at'],
        'users' => ['last_login_at', 'email_verified_at', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $to = $this->targetTimezone();

        if ($to === null) {
            return;
        }

        $this->convert(fn (string $value) => Carbon::parse($value, 'UTC')
            ->setTimezone($to)
            ->format('Y-m-d H:i:s'));
    }

    public function down(): void
    {
        $from = $this->targetTimezone();

        if ($from === null) {
            return;
        }

        $this->convert(fn (string $value) => Carbon::parse($value, $from)
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s'));
    }

    /**
     * The timezone the application now stores and displays, or null when it is
     * still UTC (nothing to convert).
     */
    private function targetTimezone(): ?string
    {
        $timezone = config('app.timezone');

        return $timezone && $timezone !== 'UTC' ? $timezone : null;
    }

    private function convert(callable $convert): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->select('id', $column)
                    ->whereNotNull($column)
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table, $column, $convert) {
                        foreach ($rows as $row) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update([$column => $convert((string) $row->{$column})]);
                        }
                    });
            }
        }
    }
};
