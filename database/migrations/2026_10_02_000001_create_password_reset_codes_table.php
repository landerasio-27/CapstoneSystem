<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The code an account is sent when it asks for a new password.
     *
     * One row to an account: asking again replaces the code rather than filing
     * a second one, so a code the office has since asked past cannot still open
     * the account. The code is kept hashed, the way the password it stands in
     * for is kept, and the row carries its own expiry and the number of tries
     * made against it — a six-digit code is short enough to sit through a few
     * guesses, so the last of them is spent rather than left open.
     *
     * The addresses are the same ones the sign-in screen accepts: the row is
     * filed under the account's own email, and every code written here belongs
     * to an account the portal would otherwise let sign in.
     *
     * The expiry is a plain datetime and not a timestamp on purpose. On a server
     * with explicit_defaults_for_timestamp off — MariaDB ships that way — the
     * first timestamp column of a table is given ON UPDATE current_timestamp(),
     * so the moment a wrong code was counted against the row the expiry moved to
     * that moment and the code died on the very next try. A datetime column has
     * no such rule, and the field only ever moves where the code is written.
     */
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
