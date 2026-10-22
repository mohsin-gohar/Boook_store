x   <?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/user', 'GET');
$request->headers->set('Accept', 'application/json');
try {
    $response = $kernel->handle($request);
    echo "STATUS: ".$response->getStatusCode()."\n";
    echo "BODY: ".substr($response->getContent(), 0, 500)."\n";
} catch (\Throwable $e) {
    echo "THROWN: ".get_class($e).": ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
