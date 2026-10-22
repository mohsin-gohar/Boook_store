<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'book_id' => (int) $this->book_id,
            'book' => $this->whenLoaded('book') ? [
                'id' => (int) $this->book->id,
                'title' => (string) $this->book->title,
                'price' => (float) $this->book->price,
                'cover_image' => (string) ($this->book->cover_image ?? ''),
            ] : null,
            'title' => (string) ($this->book?->title ?? ''),
            'price' => (float) ($this->price ?? $this->book?->price ?? 0),
            'qty' => (int) $this->quantity,
            'quantity' => (int) $this->quantity,
            'cover_image' => (string) ($this->book?->cover_image ?? ''),
        ];
    }
}
