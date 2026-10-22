<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Display a listing of books with search, filters and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()->with('category');

        // Search by title, author or category name (case-insensitive).
        if ($request->filled('search')) {
            $search = (string) $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by category.
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by author id or by author name.
        if ($request->filled('author_id')) {
            $query->where('author_id', $request->input('author_id'));
        }

        if ($request->filled('author')) {
            $query->where('author', 'like', '%'.$request->input('author').'%');
        }

        // Filter by price range (inclusive).
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        // Filter by minimum rating.
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float) $request->input('min_rating'));
        }

        // Filter by bestseller / new arrival.
        if ($request->boolean('bestsellers')) {
            $query->where('is_bestseller', true);
        }

        if ($request->boolean('new_arrivals')) {
            $query->where('is_new_arrival', true);
        }

        // Filter by stock availability.
        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        // Sorting — the client sends exactly these six values.
        $sort = (string) $request->input('sort', 'newest');

        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'price_low_high' => $query->orderBy('price', 'asc'),
            'price_high_low' => $query->orderBy('price', 'desc'),
            'popularity' => $query->orderBy('review_count', 'desc'),
            'rating' => $query->orderBy('rating', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min((int) $request->input('per_page', 20), 100);
        $books = $query->paginate($perPage);

        return ApiResponse::paginated(
            $books,
            BookResource::collection($books->items())->resolve(),
        );
    }

    /**
     * Display the specified book.
     */
    public function show(int $book): JsonResponse
    {
        $model = Book::with(['category', 'reviews.user'])->find($book);

        if (! $model) {
            return ApiResponse::error('Book not found.', 404);
        }

        return ApiResponse::success(new BookResource($model));
    }
}
