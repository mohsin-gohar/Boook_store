<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\Author;
use App\Models\Book;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PasswordResetCode;
use App\Models\Review;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookstoreApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $overrides = []): Book
    {
        $category = Category::factory()->create();

        return Book::factory()->create(array_merge([
            'category_id' => $category->id,
            'price' => 20.00,
            'stock' => 10,
        ], $overrides));
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    // ------------------------------------------------------------------ Auth

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password1!',
            'phone' => '+1234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonStructure(['data' => ['user', 'access_token', 'token_type']]);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_requires_valid_data(): void
    {
        $this->postJson('/api/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $existing = $this->createUser();

        $this->postJson('/api/register', [
            'name' => 'Jane',
            'email' => $existing->email,
            'password' => 'Password1!',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user', 'access_token', 'token_type']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->createUser();

        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_does_not_crash_under_stateful_session_auth(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->postJson('/api/logout')
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_logout_revokes_bearer_token_even_if_session_resolves_user(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->actingAs($user)->withToken($token)->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ----------------------------------------------------------------- Reviews

    public function test_user_can_submit_review(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/books/'.$book->id.'/reviews', ['rating' => 4, 'comment' => 'Good read'])
            ->assertStatus(201)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Good read');
    }

    public function test_user_cannot_review_same_book_twice(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();
        Review::create(['user_id' => $user->id, 'book_id' => $book->id, 'rating' => 3]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/books/'.$book->id.'/reviews', ['rating' => 5])
            ->assertStatus(400);
    }

    public function test_review_validates_rating_range(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/books/'.$book->id.'/reviews', ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_review_returns_404_for_missing_book(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/books/999/reviews', ['rating' => 5])
            ->assertStatus(404);
    }

    public function test_user_can_list_book_reviews(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();
        Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'Great book!',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/books/'.$book->id.'/reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.comment', 'Great book!');
    }

    // ------------------------------------------------------------------ Admin

    public function test_admin_can_list_books(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $book = $this->createBook(['title' => 'Admin Book', 'price' => 9.99]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/books')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Admin Book');
    }

    public function test_admin_can_create_book(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/books', [
                'title' => 'Admin Test Book',
                'category_id' => $this->category()->id,
                'author_id' => $this->author()->id,
                'price' => 19.99,
                'stock' => 50,
                'isbn' => '9780000000000',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Admin Test Book');

        $this->assertDatabaseHas('books', ['title' => 'Admin Test Book']);
    }

    public function test_admin_can_update_book_rating(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $book = $this->createBook(['rating' => 0, 'review_count' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/books/'.$book->id, ['rating' => 4])
            ->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.review_count', 1);

        $this->assertEquals(4, $book->fresh()->rating);
        $this->assertEquals(1, $book->fresh()->review_count);
    }

    private function category(): Category
    {
        return Category::factory()->create(['name' => 'Test Category', 'slug' => 'test-category']);
    }

    private function author(): Author
    {
        return Author::factory()->create(['name' => 'Test Author']);
    }

    // ---------------------------------------------------------------- Orders

    public function test_checkout_creates_order_from_cart(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['price' => 25.00, 'stock' => 10]);
        Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 2]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders/checkout', [
            'shipping_address' => '789 Oak Ave, Metropolis, NY 10001, USA',
        ])->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertEqualsWithDelta(50.0, $response->json('data.total_amount'), 0.01);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('carts', 0);
        $this->assertEquals(8, $book->fresh()->stock);
    }

    public function test_checkout_applies_promo_code_discount(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['price' => 25.00, 'stock' => 10]);
        Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 2]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders/checkout', [
            'shipping_address' => '789 Oak Ave, Metropolis, NY 10001, USA',
            'promo_code' => 'aptech20',
        ])->assertStatus(201)
            ->assertJsonPath('data.promo_code', 'APTECH20');

        $this->assertEqualsWithDelta(10.0, $response->json('data.discount'), 0.01);
        $this->assertEqualsWithDelta(40.0, $response->json('data.total_amount'), 0.01);

        $this->assertDatabaseHas('orders', [
            'promo_code' => 'APTECH20',
            'discount' => 10.00,
        ]);
    }

    public function test_checkout_rejects_invalid_promo_code(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['price' => 25.00, 'stock' => 10]);
        Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders/checkout', [
            'shipping_address' => '1 St',
            'promo_code' => 'NOPE',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['promo_code']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_on_empty_cart(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders/checkout', ['shipping_address' => '1 St'])
            ->assertStatus(400);
    }

    // ---------------------------------------------------------------- Orders
    public function test_checkout_requires_shipping_address(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();
        Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address']);
    }

    public function test_user_can_view_order_history(): void
    {
        $user = $this->createUser();
        $order = Order::create([
            'user_id' => $user->id,
            'subtotal' => 30.00,
            'shipping' => 0.00,
            'total' => 30.00,
            'status' => 'shipped',
            'address' => '1 St',
            'payment_method' => 'Cash on delivery',
            'order_date' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $this->createBook()->id,
            'title' => 'Item',
            'qty' => 1,
            'price' => 30.00,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('meta.total_orders', 1)
            ->assertJsonPath('data.0.status', 'shipped');
    }

    public function test_user_can_cancel_pending_order(): void
    {
        $user = $this->createUser();
        $order = Order::create([
            'user_id' => $user->id,
            'subtotal' => 20.00,
            'shipping' => 0.00,
            'total' => 20.00,
            'status' => 'pending',
            'address' => '1 St',
            'payment_method' => 'Cash on delivery',
            'order_date' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $this->createBook()->id,
            'title' => 'Item',
            'qty' => 1,
            'price' => 20.00,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders/'.$order->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    // ---------------------------------------------------------------- Books

    public function test_books_can_be_listed_with_search_and_filters(): void
    {
        $category = Category::factory()->create(['name' => 'Fantasy', 'slug' => 'fantasy']);
        Book::factory()->create(['title' => 'The Hobbit', 'author' => 'Tolkien', 'category_id' => $category->id, 'price' => 14.99]);
        Book::factory()->create(['title' => 'Dune', 'author' => 'Herbert', 'category_id' => $category->id, 'price' => 15.99]);

        $this->getJson('/api/books')->assertOk()->assertJsonPath('meta.total', 2);

        $this->getJson('/api/books?search=Hobbit')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'The Hobbit');

        $this->getJson('/api/books?category_id='.$category->id)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_book_detail_includes_reviews_and_average_rating(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();

        Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'Amazing book!',
        ]);

        $this->getJson('/api/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.title', $book->title)
            ->assertJsonPath('data.average_rating', 5);
    }

    public function test_book_list_allows_pagination_and_sorting(): void
    {
        $this->getJson('/api/books?page=2&per_page=10&sort=rating&direction=desc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total_pages', 1);
    }

    // ------------------------------------------------------------------ Cart

    public function test_user_can_add_to_cart(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['price' => 25.00, 'stock' => 10]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart/add', ['book_id' => $book->id])
            ->assertOk()
            ->assertJsonPath('data.items.0.book.id', $book->id)
            ->assertJsonPath('data.items.0.quantity', 1);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('meta.total_items', 1);
    }

    public function test_user_can_update_and_remove_cart_items(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['price' => 30.00, 'stock' => 5]);
        $item = Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/cart/update/'.$item->id, ['qty' => 2])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/cart/remove/'.$item->id)
            ->assertOk();

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_user_can_view_cart_with_total_amount(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['price' => 25.00, 'stock' => 10]);
        Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 2]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('meta.total_items', 1)
            ->assertJsonPath('meta.total_amount', 50);
    }

    public function test_cart_rejects_out_of_stock_books(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['stock' => 0]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart/add', ['book_id' => $book->id])
            ->assertStatus(400);
    }

    public function test_cart_rejects_quantity_beyond_stock(): void
    {
        $user = $this->createUser();
        $book = $this->createBook(['stock' => 2]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart/add', ['book_id' => $book->id, 'quantity' => 5])
            ->assertStatus(422);
    }

    public function test_user_cannot_modify_other_users_cart_items(): void
    {
        $owner = $this->createUser();
        $intruder = $this->createUser();
        $book = $this->createBook();
        $cartItem = Cart::create(['user_id' => $owner->id, 'book_id' => $book->id, 'quantity' => 1]);

        $this->actingAs($intruder, 'sanctum')
            ->putJson('/api/cart/update/'.$cartItem->id, ['qty' => 99])
            ->assertStatus(404);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson('/api/cart/remove/'.$cartItem->id)
            ->assertStatus(404);
    }

    // ----------------------------------------------------------------- Wishlist

    public function test_wishlist_toggle_adds_and_removes(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wishlist/toggle', ['book_id' => $book->id])
            ->assertStatus(201)
            ->assertJsonPath('data.is_in_wishlist', true);

        $this->assertDatabaseCount('wishlists', 1);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wishlist/toggle', ['book_id' => $book->id])
            ->assertOk()
            ->assertJsonPath('data.is_in_wishlist', false);

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_user_can_list_wishlist(): void
    {
        $user = $this->createUser();
        $book = $this->createBook();
        Wishlist::create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonPath('meta.total_items', 1)
            ->assertJsonPath('data.0.book.id', $book->id);
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
        $this->getJson('/api/cart')->assertStatus(401);
        $this->getJson('/api/wishlist')->assertStatus(401);
        $this->getJson('/api/orders')->assertStatus(401);
        $this->postJson('/api/orders/checkout', [])->assertStatus(401);
    }

    public function test_user_can_get_and_update_profile(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/update', [
                'name' => 'Updated Name',
                'phone' => '+9999999999',
                'photo_url' => 'https://cdn.example.com/photo.jpg',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '+9999999999');

        $this->assertDatabaseHas('users', ['name' => 'Updated Name']);
    }

    // ------------------------------------------------------------------
    // Password reset
    // ------------------------------------------------------------------

    public function test_forgot_password_sends_the_reset_code_by_mail(): void
    {
        Mail::fake();

        $user = $this->createUser();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        // The code must be delivered by e-mail — never in the response body.
        Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertDatabaseHas('password_reset_codes', ['email' => $user->email]);
    }

    public function test_forgot_password_does_not_leak_unknown_addresses(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        // The answer is identical to the one for a known address.
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If that address exists, a reset code has been sent.');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_codes', [
            'email' => 'nobody@example.com',
        ]);
    }

    public function test_user_can_reset_password_with_the_issued_code(): void
    {
        Mail::fake();

        $user = $this->createUser();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        $code = PasswordResetCode::where('email', $user->email)->value('code');

        $this->postJson('/api/auth/verify-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-password-123',
        ])->assertOk();

        // The new password works and the old one no longer does.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_reset_password_rejects_an_invalid_code(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'new-password-123',
        ])->assertStatus(422);
    }
}
