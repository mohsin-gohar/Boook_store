    <?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$u = App\Models\User::factory()->create();
$b = App\Models\Book::factory()->create([
    'category_id' => App\Models\Category::factory()->create()->id,
    'price' => 10, 'stock' => 5,
]);
App\Models\Wishlist::create(['user_id'=>$u->id,'book_id'=>$b->id]);
$req = Illuminate\Http\Request::create('/api/wishlist','GET');
$req->setUserResolver(fn() => $u);
$kernel->bootstrap();
$c = $app->make(App\Http\Controllers\WishlistController::class);
$res = $c->index($req);
echo $res->getContent(), PHP_EOL;
