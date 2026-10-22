<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An in-app notification.
 */
class NotificationResource extends JsonResource
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
            'title' => (string) ($this->title ?? ''),
            'message' => (string) ($this->message ?? ''),
            'read' => (bool) $this->read,
            'type' => (string) ($this->type ?? 'general'),
            'order_id' => (string) ($this->order_id ?? ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
