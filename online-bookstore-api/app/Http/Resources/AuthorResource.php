<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An author of one or more books.
 */
class AuthorResource extends JsonResource
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
            'name' => $this->name,
            'bio' => (string) ($this->bio ?? ''),
            'photo_url' => (string) ($this->photo_url ?? ''),
            'book_count' => (int) ($this->books_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
