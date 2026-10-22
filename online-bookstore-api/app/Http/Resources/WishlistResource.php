<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
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
            'cover_image' => (string) ($this->book?->cover_image ?? ''),
            'added_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
