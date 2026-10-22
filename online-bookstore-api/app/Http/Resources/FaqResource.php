<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A frequently-asked question.
 */
class FaqResource extends JsonResource
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
            'question' => (string) ($this->question ?? ''),
            'answer' => (string) ($this->answer ?? ''),
            'category' => (string) ($this->category ?? 'General'),
            'sort_order' => (int) ($this->sort_order ?? 0),
        ];
    }
}
