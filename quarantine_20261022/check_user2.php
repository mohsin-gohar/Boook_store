<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('password')]);
$user->setAttribute('addresses', []);
$user->setAttribute('payment_methods', []);
$user->name = 'Updated Name';
$user->phone = '+9999999999';
$user->photo_url = 'https://cdn.example.com/photo.jpg';
echo "dirty: ";
print_r($user->getDirty());
try {
    $user->save();
    echo "SAVE OK\n";
} catch (\Throwable $e) {
    echo "SAVE FAIL: ".$e->getMessage()."\n";
}
