<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved shipping address belonging to a user.
 */
class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'line1',
        'line2',
        'city',
        'zip',
        'phone',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns the address.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
