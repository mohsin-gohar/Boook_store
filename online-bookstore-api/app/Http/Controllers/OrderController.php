<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Book;
use App\Models\Cart;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Orders at or above this subtotal ship free.
     */
    public const FREE_SHIPPING_THRESHOLD = 50.00;

    /**
     * Flat shipping fee applied below the free-shipping threshold.
     */
    public const FLAT_SHIPPING_FEE = 5.99;

    /**
     * Display the signed-in user's order history, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::where('user_id', $request->user()->id)
            ->with(['items', 'user'])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        return ApiResponse::paginated(
            $orders,
            OrderResource::collection($orders->items())->resolve(),
        );
    }

    /**
     * Display one order (the owner or an admin may read it).
     */
    public function show(Request $request, int $order): JsonResponse
    {
        $model = Order::with(['items', 'user'])->find($order);

        if (! $model) {
            return ApiResponse::error('Order not found.', 404);
        }

        $user = $request->user();

        if ((int) $model->user_id !== (int) $user->id && $user->role !== 'admin') {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        return ApiResponse::success(new OrderResource($model));
    }

    /**
     * Place an order.
     *
     * The client sends the address and the payment method as labels, plus the
     * ordered lines. The server recomputes the totals from the database
     * prices, decrements the stock in a transaction and rejects the request
     * when there is not enough stock.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', 'exists:books,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        DB::beginTransaction();

        try {
            $bookIds = array_column($validated['items'], 'book_id');
            $books = Book::whereIn('id', $bookIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $lines = [];

            foreach ($validated['items'] as $item) {
                $book = $books->get($item['book_id']);

                if (! $book) {
                    DB::rollBack();

                    return ApiResponse::error('Book not found.', 422, [
                        'items' => ['One of the books in your order no longer exists.'],
                    ]);
                }

                $qty = (int) $item['qty'];

                if ($book->stock < $qty) {
                    DB::rollBack();

                    return ApiResponse::error(
                        "Not enough stock for \"{$book->title}\". Only {$book->stock} available.",
                        422,
                        ['items' => ["Not enough stock for \"{$book->title}\"."]],
                    );
                }

                $price = (float) $book->price;
                $subtotal += $price * $qty;

                $lines[] = [
                    'book' => $book,
                    'qty' => $qty,
                    'price' => $price,
                ];
            }

            $shipping = $subtotal >= self::FREE_SHIPPING_THRESHOLD
                ? 0.0
                : self::FLAT_SHIPPING_FEE;

            $now = now();

            $order = Order::create([
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $subtotal + $shipping,
                'status' => 'pending',
                'address' => $validated['address'],
                'payment_method' => $validated['payment_method'],
                'timeline' => [[
                    'status' => 'pending',
                    'at' => $now->toIso8601String(),
                    'note' => 'Order placed.',
                ]],
                'order_date' => $now,
                'delivery_date' => $now->copy()->addDays(3),
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $line['book']->id,
                    'title' => $line['book']->title,
                    'cover_image' => $line['book']->cover_image,
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                ]);

                $line['book']->decrement('stock', $line['qty']);
            }

            // The order is placed from the cart, so the cart is emptied.
            Cart::where('user_id', $user->id)->delete();

            Notification::create([
                'user_id' => $user->id,
                'title' => 'Order placed',
                'message' => "Your order {$order->id} is now pending.",
                'read' => false,
                'type' => 'order',
                'order_id' => (string) $order->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return ApiResponse::error('Could not place your order.', 500);
        }

        $order->load(['items', 'user']);

        return ApiResponse::success(
            new OrderResource($order),
            'Order placed.',
            status: 201,
        );
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $model = Order::with('items')->find($order);

        if (! $model) {
            return ApiResponse::error('Order not found.', 404);
        }

        if ((int) $model->user_id !== (int) $user->id && $user->role !== 'admin') {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        if (! in_array($model->status, ['pending', 'confirmed'], true)) {
            return ApiResponse::error(
                'This order can no longer be cancelled.',
                400,
            );
        }

        $reason = $validated['reason'] ?? 'Cancelled by the customer.';
        $now = now();

        $timeline = $model->timeline ?? [];
        $timeline[] = [
            'status' => 'cancelled',
            'at' => $now->toIso8601String(),
            'note' => $reason,
        ];

        $model->update([
            'status' => 'cancelled',
            'cancelled_reason' => $reason,
            'timeline' => $timeline,
        ]);

        // The reserved stock goes back on the shelf.
        foreach ($model->items as $item) {
            Book::where('id', $item->book_id)->increment('stock', $item->qty);
        }

        $model->load(['items', 'user']);

        return ApiResponse::success(
            new OrderResource($model),
            'Order cancelled.',
        );
    }

    /**
     * Place an order directly from the user's cart (the checkout flow).
     *
     * The cart itself is the source of the lines; the request only carries
     * the shipping address (and optionally the payment method). Reuses the
     * `store` flow so the totals, the stock decrement and the cart clearing
     * behave identically to a manual order.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address' => ['required', 'string', 'max:500'],
            'payment_method' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $cartItems = Cart::where('user_id', $user->id)->with('book')->get();

        if ($cartItems->isEmpty()) {
            return ApiResponse::error('Your cart is empty.', 400);
        }

        $addressLine = $validated['shipping_address'];

        $request->merge([
            'address' => $addressLine,
            'payment_method' => $validated['payment_method'] ?? 'Cash on delivery',
            'items' => $cartItems->map(fn (Cart $item) => [
                'book_id' => $item->book_id,
                'qty' => $item->quantity,
            ])->values()->all(),
        ]);

        return $this->store($request);
    }
}
