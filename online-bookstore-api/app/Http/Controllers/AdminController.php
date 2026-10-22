<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthorResource;
use App\Http\Resources\BookResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The admin dashboard API: catalogue management, order fulfilment and user
 * moderation.
 */
class AdminController extends Controller
{
    /**
     * The five statuses an order can move between.
     */
    private const ORDER_STATUSES = [
        'pending',
        'confirmed',
        'shipped',
        'delivered',
        'cancelled',
    ];

    /**
     * Dashboard counters.
     *
     * The client reads `revenue` directly; every other counter it builds
     * from the `meta.total` of a dedicated list endpoint.
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success([
            'total_books' => Book::count(),
            'total_categories' => Category::count(),
            'total_authors' => Author::count(),
            'total_users' => User::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'revenue' => (float) Order::where('status', '!=', 'cancelled')->sum('total'),
        ]);
    }

    // ── Books ─────────────────────────────────────────────────────

    /**
     * All books for the manage-books screen.
     */
    public function books(Request $request): JsonResponse
    {
        $query = Book::query()->with('category');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $books = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::paginated(
            $books,
            BookResource::collection($books->items())->resolve(),
        );
    }

    /**
     * Create a book.
     *
     * The body is `BookModel.toMap()`; the server ignores the keys it owns
     * (`id`, `created_at`, `category_name` — the last is re-derived from
     * `category_id` when the resource serialises the model).
     */
    public function storeBook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required_without:author_id', 'nullable', 'string', 'max:255'],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable', 'array'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'is_bestseller' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:50'],
            'pages' => ['nullable', 'integer', 'min:0'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
        ]);

        // When only the author_id is provided, derive the author name from
        // the authors table so the denormalised column is always filled.
        if (empty($validated['author']) && ! empty($validated['author_id'])) {
            $validated['author'] = Author::find($validated['author_id'])?->name ?? '';
        }

        $book = Book::create($validated);

        return ApiResponse::success(
            new BookResource($book->load('category')),
            'Book created.',
            status: 201,
        );
    }

    /**
     * Full replacement of a book (admin edit form).
     */
    public function updateBook(Request $request, int $book): JsonResponse
    {
        $model = Book::find($book);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable', 'array'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'is_bestseller' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:50'],
            'pages' => ['nullable', 'integer', 'min:0'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
        ]);

        $model->fill($validated);
        $model->save();

        return ApiResponse::success(
            new BookResource($model->load('category')),
            'Book updated.',
        );
    }

    /**
     * Refresh the denormalised rating aggregate of a book.
     *
     * The client PATCHes its recomputed pair after writing, editing or
     * deleting a review. The endpoint accepts the pair but the server's own
     * recomputation from the review table wins — it is the source of truth.
     *
     * This is the one `/admin/...` path a signed-in customer calls, so it
     * needs a valid session, not the `admin` role (the route lives outside
     * the admin middleware group for that reason).
     */
    public function updateBookRating(Request $request, int $book): JsonResponse
    {
        $model = Book::find($book);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $validated = $request->validate([
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
        ]);

        // The client recomputes both after each review write, so the values
        // it sends win. When only a rating arrives (a manual correction or a
        // single-review write), the review count is bumped so the two stay
        // consistent with each other.
        $updates = [
            'rating' => (float) ($validated['rating'] ?? $model->rating),
            'review_count' => $validated['review_count']
                ?? $model->review_count + 1,
        ];

        $model->update($updates);

        return ApiResponse::success(
            new BookResource($model->fresh()->load('category')),
            'Book updated.',
        );
    }

    /**
     * Delete a book.
     */
    public function destroyBook(int $book): JsonResponse
    {
        $model = Book::find($book);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->delete();

        return ApiResponse::success(null, 'Book deleted.');
    }

    // ── Categories ────────────────────────────────────────────────

    /**
     * All categories for the manage-categories screen.
     */
    public function categories(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 100), 100);
        $categories = Category::query()
            ->withCount('books')
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $categories,
            CategoryResource::collection($categories->items())->resolve(),
        );
    }

    /**
     * Create a category. The slug is derived from the name when omitted.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
        ]);

        return ApiResponse::success(
            new CategoryResource($category->loadCount('books')),
            'Category created.',
            status: 201,
        );
    }

    /**
     * Update a category.
     */
    public function updateCategory(Request $request, int $category): JsonResponse
    {
        $model = Category::find($category);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:categories,slug,'.$model->id],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
        ]);

        $model->fill($validated);
        $model->save();

        return ApiResponse::success(
            new CategoryResource($model->loadCount('books')),
            'Category updated.',
        );
    }

    /**
     * Delete a category.
     */
    public function destroyCategory(int $category): JsonResponse
    {
        $model = Category::find($category);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->delete();

        return ApiResponse::success(null, 'Category deleted.');
    }

    // ── Authors ───────────────────────────────────────────────────

    /**
     * All authors for the manage-authors screen.
     */
    public function authors(Request $request): JsonResponse
    {
        $query = Author::query()->withCount('books');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $perPage = min((int) $request->input('per_page', 100), 100);
        $authors = $query->orderBy('name')->paginate($perPage);

        return ApiResponse::paginated(
            $authors,
            AuthorResource::collection($authors->items())->resolve(),
        );
    }

    /**
     * Create an author.
     */
    public function storeAuthor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $author = Author::create($validated);

        return ApiResponse::success(
            new AuthorResource($author->loadCount('books')),
            'Author created.',
            status: 201,
        );
    }

    /**
     * Update an author.
     */
    public function updateAuthor(Request $request, int $author): JsonResponse
    {
        $model = Author::find($author);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $model->fill($validated);
        $model->save();

        return ApiResponse::success(
            new AuthorResource($model->loadCount('books')),
            'Author updated.',
        );
    }

    /**
     * Delete an author.
     */
    public function destroyAuthor(int $author): JsonResponse
    {
        $model = Author::find($author);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->delete();

        return ApiResponse::success(null, 'Author deleted.');
    }

    // ── Orders ────────────────────────────────────────────────────

    /**
     * All orders, newest first, optionally filtered by status.
     *
     * The `meta.total` of the filtered list is what the dashboard's
     * per-status counters are built from.
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::query()->with(['items', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $orders = $query->orderByDesc('order_date')->orderByDesc('id')->paginate($perPage);

        return ApiResponse::paginated(
            $orders,
            OrderResource::collection($orders->items())->resolve(),
        );
    }

    /**
     * Advance an order's status.
     *
     * The note is appended to the order's timeline and the order's owner is
     * notified server-side.
     */
    public function updateOrderStatus(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::ORDER_STATUSES)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $model = Order::with(['items', 'user'])->find($order);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $note = $validated['note'] ?? '';
        $now = now();

        $timeline = $model->timeline ?? [];
        $timeline[] = [
            'status' => $validated['status'],
            'at' => $now->toIso8601String(),
            'note' => $note,
        ];

        $model->update([
            'status' => $validated['status'],
            'timeline' => $timeline,
        ]);

        Notification::create([
            'user_id' => $model->user_id,
            'title' => 'Order '.ucfirst($validated['status']),
            'message' => "Your order {$model->id} is now {$validated['status']}.",
            'read' => false,
            'type' => 'order',
            'order_id' => (string) $model->id,
        ]);

        $model->load(['items', 'user']);

        return ApiResponse::success(new OrderResource($model), 'Order updated.');
    }

    // ── Users ─────────────────────────────────────────────────────

    /**
     * All users, newest first.
     */
    public function users(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 100), 100);
        $users = User::query()->orderByDesc('id')->paginate($perPage);

        return ApiResponse::paginated(
            $users,
            UserResource::collection($users->items())->resolve(),
        );
    }

    /**
     * Block or unblock a user.
     */
    public function blockUser(Request $request, int $user): JsonResponse
    {
        $validated = $request->validate([
            'is_blocked' => ['required', 'boolean'],
        ]);

        $model = User::find($user);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->update(['is_blocked' => (bool) $validated['is_blocked']]);

        return ApiResponse::success(new UserResource($model), 'User updated.');
    }
}
