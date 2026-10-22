<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Book;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * All reviews, newest first (used by the admin dashboard counter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()->with('user')->latest();

        $perPage = min((int) $request->input('per_page', 20), 100);
        $reviews = $query->paginate($perPage);

        return ApiResponse::paginated(
            $reviews,
            ReviewResource::collection($reviews->items())->resolve(),
        );
    }

    /**
     * The reviews of one book, newest first.
     */
    public function forBook(Request $request, int $book): JsonResponse
    {
        $model = Book::find($book);

        if (! $model) {
            return ApiResponse::error('Book not found.', 404);
        }

        // The client reads this list as a plain array in `data`, but the
        // per_page parameter it sends (50, or 200 for the aggregate refresh)
        // is still honoured.
        $perPage = min((int) $request->input('per_page', 50), 200);
        $reviews = Review::where('book_id', $book)
            ->with('user')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $reviews,
            ReviewResource::collection($reviews->items())->resolve(),
        );
    }

    /**
     * Store a new review for a book.
     */
    public function store(Request $request, int $book): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = Book::find($book);

        if (! $model) {
            return ApiResponse::error('Book not found.', 404);
        }

        $user = $request->user();

        // One review per user per book.
        $existing = Review::where('user_id', $user->id)
            ->where('book_id', $book)
            ->first();

        if ($existing) {
            return ApiResponse::error(
                'You have already reviewed this book.',
                400,
                ['book_id' => ['You have already reviewed this book.']],
            );
        }

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'likes' => 0,
            'liked_by' => [],
        ]);

        $this->syncBookRating($book);

        $review->load('user');

        return ApiResponse::success(
            new ReviewResource($review),
            'Review published.',
            status: 201,
        );
    }

    /**
     * Update one of the signed-in user's own reviews.
     */
    public function update(Request $request, int $review): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = Review::find($review);

        if (! $model) {
            return ApiResponse::error('Review not found.', 404);
        }

        if ((int) $model->user_id !== (int) $request->user()->id) {
            return ApiResponse::error(
                'You can only edit your own review.',
                403,
            );
        }

        $model->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $this->syncBookRating($model->book_id);

        $model->load('user');

        return ApiResponse::success(new ReviewResource($model), 'Review updated.');
    }

    /**
     * Delete one of the signed-in user's own reviews.
     */
    public function destroy(Request $request, int $review): JsonResponse
    {
        $model = Review::find($review);

        if (! $model) {
            return ApiResponse::error('Review not found.', 404);
        }

        if ((int) $model->user_id !== (int) $request->user()->id) {
            return ApiResponse::error(
                'You can only delete your own review.',
                403,
            );
        }

        $bookId = $model->book_id;
        $model->delete();

        $this->syncBookRating($bookId);

        return ApiResponse::success(null, 'Review deleted.');
    }

    /**
     * Toggle the signed-in user's like on a review.
     */
    public function toggleLike(Request $request, int $review): JsonResponse
    {
        $model = Review::find($review);

        if (! $model) {
            return ApiResponse::error('Review not found.', 404);
        }

        $uid = (string) $request->user()->id;
        $likedBy = $model->liked_by ?? [];

        if (in_array($uid, $likedBy, true)) {
            $likedBy = array_values(array_filter(
                $likedBy,
                fn (string $id) => $id !== $uid,
            ));
        } else {
            $likedBy[] = $uid;
        }

        $model->update([
            'liked_by' => array_values($likedBy),
            'likes' => count($likedBy),
        ]);

        $model->load('user');

        return ApiResponse::success(new ReviewResource($model));
    }

    /**
     * Recompute a book's denormalised rating and review count.
     */
    private function syncBookRating(int $bookId): void
    {
        $reviews = Review::where('book_id', $bookId)->get();

        $rating = $reviews->isEmpty()
            ? 0
            : round((float) $reviews->avg('rating'), 2);

        Book::where('id', $bookId)->update([
            'rating' => $rating,
            'review_count' => $reviews->count(),
        ]);
    }
}
