<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishlistResource;
use App\Models\Book;
use App\Models\Wishlist;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display the signed-in user's wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $payload = $this->wishlistPayload($request->user()->id);

        return ApiResponse::success(
            $payload,
            'Success',
            ['total_items' => count($payload)],
        );
    }

    /**
     * Add a book to the wishlist.
     */
    public function add(Request $request, int $book): JsonResponse
    {
        $model = Book::find($book);

        if (! $model) {
            return ApiResponse::error('Book not found.', 404);
        }

        $user = $request->user();
        $existing = Wishlist::where('user_id', $user->id)
            ->where('book_id', $model->id)
            ->first();

        if (! $existing) {
            Wishlist::create([
                'user_id' => $user->id,
                'book_id' => $model->id,
                'price' => $model->price,
            ]);
        }

        return ApiResponse::success(
            $this->wishlistPayload($user->id),
            'Saved to wishlist.',
        );
    }

    /**
     * Remove a book from the wishlist.
     */
    public function remove(Request $request, int $book): JsonResponse
    {
        $user = $request->user();

        Wishlist::where('user_id', $user->id)
            ->where('book_id', $book)
            ->delete();

        return ApiResponse::success(
            $this->wishlistPayload($user->id),
            'Removed from wishlist.',
        );
    }

    /**
     * Empty the wishlist.
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        Wishlist::where('user_id', $user->id)->delete();

        return ApiResponse::success(
            $this->wishlistPayload($user->id),
            'Your wishlist is empty.',
        );
    }

    /**
     * Toggle a book in the wishlist: add when missing, remove when present.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $user = $request->user();
        $existing = Wishlist::where('user_id', $user->id)
            ->where('book_id', $validated['book_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::success([
                'book_id' => (string) $validated['book_id'],
                'is_in_wishlist' => false,
            ], 'Removed from wishlist.');
        }

        $model = Book::find($validated['book_id']);

        Wishlist::create([
            'user_id' => $user->id,
            'book_id' => $validated['book_id'],
            'price' => $model?->price,
        ]);

        return ApiResponse::success([
            'book_id' => (string) $validated['book_id'],
            'is_in_wishlist' => true,
        ], 'Saved to wishlist.', status: 201);
    }

    /**
     * The whole wishlist, in the shape the client expects.
     */
    private function wishlistPayload(int $userId): array
    {
        $items = Wishlist::where('user_id', $userId)
            ->with('book')
            ->get()
            ->map(fn (Wishlist $item) => (new WishlistResource($item))->resolve())
            ->values()
            ->all();

        return $items;
    }
}
