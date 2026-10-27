<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Extra profile data supplied by the caller (the saved addresses and
     * payment methods loaded for `/api/user`) — merged into the output.
     */
    private array $extra = [];

    public function __construct($resource, mixed $extra = [])
    {
        parent::__construct($resource);

        // Laravel's collection mapper passes the array key as the second
        // constructor argument, so only accept it when it really is a payload.
        $this->extra = is_array($extra) ? $extra : [];
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return array_merge([
            'id' => $this->id,
            'uid' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => (string) ($this->phone ?? ''),
            'photo_url' => (string) ($this->photo_url ?? ''),
            'role' => $this->role ?? 'customer',
            'is_blocked' => (bool) $this->is_blocked,
            'created_at' => $this->created_at?->toIso8601String(),
            'addresses' => $this->extra['addresses'] ?? [],
            'payment_methods' => $this->extra['payment_methods'] ?? [],
        ], $this->extra);
    }
}
