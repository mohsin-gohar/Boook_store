<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
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
            'id' => (string) $this->id,
            'category_id' => (string) ($this->category_id ?? ''),
            'category_name' => $this->category?->name ?? '',
            'title' => $this->title,
            'author' => $this->author,
            'author_id' => (string) ($this->author_id ?? ''),
            'isbn' => (string) ($this->isbn ?? ''),
            'description' => (string) ($this->description ?? ''),
            'price' => (float) $this->price,
            'stock' => (int) $this->stock,
            'cover_image' => (string) ($this->cover_image ?? ''),
            'images' => $this->images ?? [],
            'rating' => (float) $this->rating,
            'average_rating' => $this->relationLoaded('reviews')
                ? (float) ($this->reviews->isEmpty() ? $this->rating : round($this->reviews->avg('rating'), 2))
                : (float) $this->rating,
            'review_count' => (int) $this->review_count,
            'is_bestseller' => (bool) $this->is_bestseller,
            'is_new_arrival' => (bool) $this->is_new_arrival,
            'language' => (string) ($this->language ?? 'English'),
            'pages' => (int) ($this->pages ?? 0),
            'publisher' => (string) ($this->publisher ?? ''),
            'release_date' => $this->release_date?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
