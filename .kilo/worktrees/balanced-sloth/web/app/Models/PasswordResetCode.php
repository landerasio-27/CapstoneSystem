<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * The code an account is sent when it asks for a new password.
 *
 * The code itself is never stored: what is kept is its hash, as the password it
 * stands in for is kept, so a copy of the table is not a ring of keys to every
 * account in it. A code is spent the moment it is accepted, and it is only
 * accepted while it is still standing and has not been tried too many times —
 * six digits are short enough to guess at, so the tries are counted rather than
 * left open (see MAX_ATTEMPTS).
 */
#[Fillable(['email', 'code', 'attempts', 'expires_at'])]
class PasswordResetCode extends Model
{
    /** How long a code stands before the account has to ask for another. */
    public const EXPIRES_MINUTES = 10;

    /** How many times one code may be brought back before it is spent. */
    public const MAX_ATTEMPTS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Send an account a fresh code, replacing whatever it was holding. Returns
     * the code in the clear, which is the only time it exists outside the mail
     * that carries it — the row keeps the hash and nothing else.
     */
    public static function issue(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        static::updateOrCreate(
            ['email' => $email],
            [
                'code' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            ],
        );

        return $code;
    }

    /**
     * Take a code an account has brought back: true only where it is the code
     * that was sent, still standing, and not yet worn out by tries. A code that
     * is accepted is spent here — one code opens the account once — while a
     * wrong one is only counted against the code it was tried on.
     */
    public static function verify(string $email, string $code): bool
    {
        $row = static::where('email', $email)->first();

        if ($row === null) {
            return false;
        }

        if ($row->expires_at->isPast()) {
            $row->delete();

            return false;
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $row->code)) {
            $row->increment('attempts');

            return false;
        }

        $row->delete();

        return true;
    }

    /**
     * Give up an account's code: it has been used, or the account has asked for
     * a new password and there is nothing left for the old code to open.
     */
    public static function clear(string $email): void
    {
        static::where('email', $email)->delete();
    }
}
