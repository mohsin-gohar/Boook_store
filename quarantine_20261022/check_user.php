<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Emulate what happens in the test
$path = 'app/Http/Controllers/AuthController.php';
$src = file_get_contents($path);
$lines = explode("\n", $src);
foreach ($lines as $i => $line) {
    if (preg_match('/setAttribute|syncOriginal|addresses|payment_methods|updateMe/', $line)) {
        echo ($i+1).": ".rtrim($line)."\n";
    }
}
