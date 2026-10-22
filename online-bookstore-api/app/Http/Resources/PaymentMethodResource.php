<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A saved payment method (metadata only).
 */
class PaymentMethodResource extends JsonResource
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
            'type' => (string) ($this->type ?? 'Visa'),
            'last4' => (string) ($this->last4 ?? ''),
            'holder_name' => (string) ($this->holder_name ?? ''),
            'expiry' => (string) ($this->expiry ?? ''),
            'is_default' => (bool) $this->is_default,
        ];
    }
}
