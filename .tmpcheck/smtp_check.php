<?php

/* Scratch: does the office mailbox actually take a code? One real send. */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Mail\ResetPasswordCode;
use Illuminate\Support\Facades\Mail;

echo 'mailer: '.config('mail.default').' host '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')
    .' user '.config('mail.mailers.smtp.username')."\n";

try {
    $sent = Mail::to('isusupply@gmail.com')->send(new ResetPasswordCode('123456', 10, 'Supply Office (test send)'));
    echo "SMTP send OK\n";
} catch (Throwable $e) {
    echo 'SMTP send FAILED: '.get_class($e).': '.$e->getMessage()."\n";
}
