<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A saved shipping address.
 */
class AddressResource extends JsonResource
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
            'label' => (string) ($this->label ?? 'Home'),
            'line1' => (string) ($this->line1 ?? ''),
            'line2' => (string) ($this->line2 ?? ''),
            'city' => (string) ($this->city ?? ''),
            'zip' => (string) ($this->zip ?? ''),
            'phone' => (string) ($this->phone ?? ''),
            'is_default' => (bool) $this->is_default,
        ];
    }
}
