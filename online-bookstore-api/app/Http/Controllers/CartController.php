<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Models\Book;
use App\Models\Cart;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display the signed-in user's cart.
     */
    public function index(Request $request): JsonResponse
    {
        $payload = $this->cartPayload($request->user()->id);

        return ApiResponse::success($payload, 'Success', $payload['meta']);
    }

    /**
     * Add a book to the user's cart.
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $book = Book::find($validated['book_id']);

        if ($book->stock <= 0) {
            return ApiResponse::error('This book is out of stock.', 400);
        }

        $qty = (int) ($validated['quantity'] ?? $validated['qty'] ?? 1);
        $existing = Cart::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->first();

        $newQuantity = $qty + ($existing?->quantity ?? 0);

        if ($newQuantity > $book->stock) {
            return ApiResponse::error(
                "Only {$book->stock} items available in stock.",
                422,
                ['quantity' => ["Only {$book->stock} items available in stock."]],
            );
        }

        if ($existing) {
            $existing->update([
                'quantity' => $newQuantity,
                'price' => $book->price,
            ]);
        } else {
            Cart::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                // The unit price is captured when the book is added.
                'price' => $book->price,
                'quantity' => $qty,
            ]);
        }

        return ApiResponse::success(
            $this->cartPayload($user->id),
            'Added to cart.',
        );
    }

    /**
     * Update the quantity of one cart line.
     */
    public function update(Request $request, int $book): JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $user = $request->user();
        $item = Cart::where('user_id', $user->id)
            ->where('book_id', $book)
            ->first();

        if (! $item) {
            return ApiResponse::error('That item is not in your cart.', 404);
        }

        $qty = (int) $validated['qty'];

        // A quantity of zero removes the line.
        if ($qty === 0) {
            $item->delete();

            return ApiResponse::success($this->cartPayload($user->id));
        }

        $stock = (int) (Book::where('id', $book)->value('stock') ?? 0);

        if ($qty > $stock) {
            return ApiResponse::error(
                "Only {$stock} items available in stock.",
                400,
            );
        }

        $item->update(['quantity' => $qty]);

        return ApiResponse::success($this->cartPayload($user->id));
    }

    /**
     * Remove one line from the cart.
     */
    public function remove(Request $request, int $book): JsonResponse
    {
        $user = $request->user();

        Cart::where('user_id', $user->id)
            ->where('book_id', $book)
            ->delete();

        return ApiResponse::success(
            $this->cartPayload($user->id),
            'Item removed.',
        );
    }

    /**
     * Legacy alias: update a cart line by its own row id instead of the book
     * id. Other users' rows simply do not exist for the caller.
     */
    public function updateByItem(Request $request, int $cartItem): JsonResponse
    {
        $user = $request->user();
        $item = Cart::where('user_id', $user->id)->find($cartItem);

        if (! $item) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        return $this->update($request, (int) $item->book_id);
    }

    /**
     * Legacy alias: remove a cart line by its own row id.
     */
    public function removeByItem(Request $request, int $cartItem): JsonResponse
    {
        $user = $request->user();
        $item = Cart::where('user_id', $user->id)->find($cartItem);

        if (! $item) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $item->delete();

        return ApiResponse::success($this->cartPayload($user->id), 'Item removed.');
    }

    /**
     * Empty the cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        Cart::where('user_id', $user->id)->delete();

        return ApiResponse::success(
            $this->cartPayload($user->id),
            'Your cart is empty.',
        );
    }

    /**
     * The whole cart, in the shape the client expects.
     *
     * The cart carries lines only — the subtotal, the shipping and the total
     * are computed by the client from the lines.
     */
    private function cartPayload(int $userId): array
    {
        $items = Cart::where('user_id', $userId)
            ->with('book')
            ->get()
            ->map(fn (Cart $item) => (new CartResource($item))->resolve())
            ->values()
            ->all();

        return [
            'user_id' => (string) $userId,
            'items' => $items,
            'meta' => [
                'total_items' => count($items),
                'total_amount' => round(collect($items)->sum(fn ($i) => $i['price'] * $i['qty']), 2),
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
