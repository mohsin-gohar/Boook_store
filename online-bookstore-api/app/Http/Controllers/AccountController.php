<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Http\Resources\PaymentMethodResource;
use App\Models\Address;
use App\Models\PaymentMethod;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages the signed-in user's saved addresses and payment methods.
 *
 * Only payment **metadata** is stored — brand, last four digits, holder name
 * and expiry. Full card numbers and CVV are never accepted.
 */
class AccountController extends Controller
{
    // ── Addresses ─────────────────────────────────────────────────

    /**
     * The user's saved addresses, default first.
     */
    public function addresses(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (Address $address) => (new AddressResource($address))->resolve())
            ->values()
            ->all();

        return ApiResponse::success($addresses);
    }

    /**
     * Save a new address.
     */
    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $address = Address::create([
            'user_id' => $request->user()->id,
            'label' => $validated['label'] ?? 'Home',
            'line1' => $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'city' => $validated['city'],
            'zip' => $validated['zip'],
            'phone' => $validated['phone'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        $this->syncDefaultAddress($address->user_id, $address);

        return ApiResponse::success(
            new AddressResource($address),
            'Address saved.',
            status: 201,
        );
    }

    /**
     * Update one of the user's own addresses.
     */
    public function updateAddress(Request $request, int $address): JsonResponse
    {
        $model = Address::where('user_id', $request->user()->id)->find($address);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $model->fill($validated);
        $model->save();

        $this->syncDefaultAddress($model->user_id, $model);

        return ApiResponse::success(new AddressResource($model), 'Address updated.');
    }

    /**
     * Delete one of the user's own addresses.
     */
    public function destroyAddress(Request $request, int $address): JsonResponse
    {
        $model = Address::where('user_id', $request->user()->id)->find($address);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->delete();

        return ApiResponse::success(null, 'Address deleted.');
    }

    // ── Payment methods ───────────────────────────────────────────

    /**
     * The user's saved payment methods (metadata only), default first.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = PaymentMethod::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethod $method) => (new PaymentMethodResource($method))->resolve())
            ->values()
            ->all();

        return ApiResponse::success($methods);
    }

    /**
     * Save a payment method.
     *
     * Only metadata is accepted — `last4` is capped at four characters so a
     * full card number can never be stored.
     */
    public function storePaymentMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'last4' => ['nullable', 'string', 'max:4'],
            'holder_name' => ['nullable', 'string', 'max:255'],
            'expiry' => ['nullable', 'string', 'max:5'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $method = PaymentMethod::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'last4' => $validated['last4'] ?? null,
            'holder_name' => $validated['holder_name'] ?? null,
            'expiry' => $validated['expiry'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        $this->syncDefaultPaymentMethod($method->user_id, $method);

        return ApiResponse::success(
            new PaymentMethodResource($method),
            'Payment method saved.',
            status: 201,
        );
    }

    /**
     * Update one of the user's own payment methods.
     */
    public function updatePaymentMethod(Request $request, int $method): JsonResponse
    {
        $model = PaymentMethod::where('user_id', $request->user()->id)->find($method);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'last4' => ['nullable', 'string', 'max:4'],
            'holder_name' => ['nullable', 'string', 'max:255'],
            'expiry' => ['nullable', 'string', 'max:5'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $model->fill($validated);
        $model->save();

        $this->syncDefaultPaymentMethod($model->user_id, $model);

        return ApiResponse::success(new PaymentMethodResource($model), 'Payment method updated.');
    }

    /**
     * Delete one of the user's own payment methods.
     */
    public function destroyPaymentMethod(Request $request, int $method): JsonResponse
    {
        $model = PaymentMethod::where('user_id', $request->user()->id)->find($method);

        if (! $model) {
            return ApiResponse::error('The requested item was not found.', 404);
        }

        $model->delete();

        return ApiResponse::success(null, 'Payment method deleted.');
    }

    // ── Internals ─────────────────────────────────────────────────

    /**
     * Keeps a single default address per user.
     */
    private function syncDefaultAddress(int $userId, Address $current): void
    {
        if (! $current->is_default) {
            return;
        }

        Address::where('user_id', $userId)
            ->whereKeyNot($current->id)
            ->update(['is_default' => false]);
    }

    /**
     * Keeps a single default payment method per user.
     */
    private function syncDefaultPaymentMethod(int $userId, PaymentMethod $current): void
    {
        if (! $current->is_default) {
            return;
        }

        PaymentMethod::where('user_id', $userId)
            ->whereKeyNot($current->id)
            ->update(['is_default' => false]);
    }
}
