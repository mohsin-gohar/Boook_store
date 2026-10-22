<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'book_id' => (string) $this->book_id,
            'user_id' => (string) $this->user_id,
            'user_name' => (string) ($this->user?->name ?? ''),
            'user_photo' => (string) ($this->user?->photo_url ?? ''),
            'rating' => (int) $this->rating,
            'comment' => (string) ($this->comment ?? ''),
            'likes' => (int) $this->likes,
            'liked_by' => $this->liked_by ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
