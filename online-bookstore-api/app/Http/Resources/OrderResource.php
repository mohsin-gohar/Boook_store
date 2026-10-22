<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $items = collect($this->whenLoaded('items'))
            ->map(fn ($item) => array_merge(
                (new OrderItemResource($item))->resolve(),
                ['quantity' => (int) $item->qty, 'amount' => (float) $item->price * (int) $item->qty],
            ))
            ->values()
            ->all();

        $total = (float) ($this->total ?? $this->subtotal);

        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'user_name' => (string) ($this->user?->name ?? ''),
            'items' => $items,
            'subtotal' => (float) $this->subtotal,
            'shipping' => (float) $this->shipping,
            'total' => $total,
            'total_amount' => $total,
            'status' => $this->status,
            'address' => (string) ($this->address ?? ''),
            'payment_method' => (string) ($this->payment_method ?? ''),
            'order_date' => ($this->order_date ?? $this->created_at)?->toIso8601String(),
            'delivery_date' => $this->delivery_date?->toIso8601String(),
            'timeline' => $this->timeline ?? [],
            'cancelled_reason' => (string) ($this->cancelled_reason ?? ''),
        ];
    }
}
