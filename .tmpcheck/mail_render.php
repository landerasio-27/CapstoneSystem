<?php

/* Scratch: render the reset-code mail on its own, to look at. */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$html = view('emails.password-reset-code', [
    'code' => '482913',
    'minutesToExpire' => App\Models\PasswordResetCode::EXPIRES_MINUTES,
    'accountName' => 'Juan Dela Cruz',
])->render();

file_put_contents(__DIR__.'/reset-code-mail.html', $html);
echo 'rendered '.strlen($html)." bytes\n";
