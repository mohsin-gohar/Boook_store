<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'book_id' => (string) $this->book_id,
            'title' => (string) ($this->title ?? $this->book?->title ?? ''),
            'price' => (float) $this->price,
            'qty' => (int) $this->qty,
            'cover_image' => (string) ($this->cover_image ?? $this->book?->cover_image ?? ''),
        ];
    }
}
